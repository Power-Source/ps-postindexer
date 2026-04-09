<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recent_Global_Comments_Feed_Widget extends WP_Widget {

	const NAME = __CLASS__;
	const TEXT_DOMAIN = 'postindexer';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'recent_global_comments_feed',
			__( 'Letzte globale Kommentare (Feed)', self::TEXT_DOMAIN ),
			array(
				'classname' => 'recent_global_comments_feed_widget',
				'description' => __( 'Zeigt einen Link zum globalen Kommentare-Feed.', self::TEXT_DOMAIN ),
			)
		);
	}

	/**
	 * Render widget output.
	 *
	 * @param array $args Widget args.
	 * @param array $instance Saved instance.
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, self::get_default_instance() );
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
		$rss_image = ( 'hide' === $instance['rss_image'] ) ? 'hide' : 'show';
		$feed_url = rgcf_get_feed_url();

		echo $args['before_widget'];
		echo $args['before_title'];
		echo '<a href="' . esc_url( $feed_url ) . '">';
		if ( 'show' === $rss_image ) {
			echo '<img src="' . esc_url( includes_url( 'images/rss.png' ) ) . '" alt="" /> ';
		}
		echo esc_html( $title );
		echo '</a>';
		echo $args['after_title'];
		echo $args['after_widget'];
	}

	/**
	 * Render widget settings form.
	 *
	 * @param array $instance Saved instance.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, self::get_default_instance() );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Titel', self::TEXT_DOMAIN ); ?>:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" type="text">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'rss_image' ) ); ?>"><?php esc_html_e( 'RSS-Bild', self::TEXT_DOMAIN ); ?>:</label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'rss_image' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'rss_image' ) ); ?>" class="widefat">
				<option value="show" <?php selected( $instance['rss_image'], 'show' ); ?>><?php esc_html_e( 'Anzeigen', self::TEXT_DOMAIN ); ?></option>
				<option value="hide" <?php selected( $instance['rss_image'], 'hide' ); ?>><?php esc_html_e( 'Ausblenden', self::TEXT_DOMAIN ); ?></option>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitize and persist settings.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = self::get_default_instance();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : $instance['title'];
		$instance['rss_image'] = ( isset( $new_instance['rss_image'] ) && 'hide' === $new_instance['rss_image'] ) ? 'hide' : 'show';
		return $instance;
	}

	/**
	 * Get defaults with fallback from legacy option.
	 *
	 * @return array
	 */
	private static function get_default_instance() {
		$defaults = array(
			'title' => __( 'Kommentare-Feed der Website', self::TEXT_DOMAIN ),
			'rss_image' => 'show',
		);

		$legacy = get_option( 'widget_recent_global_comments_feed', array() );
		if ( is_array( $legacy ) ) {
			if ( ! empty( $legacy['recent-global-comments-feed-title'] ) ) {
				$defaults['title'] = sanitize_text_field( $legacy['recent-global-comments-feed-title'] );
			}
			if ( ! empty( $legacy['recent-global-comments-feed-rss-image'] ) ) {
				$defaults['rss_image'] = ( 'hide' === $legacy['recent-global-comments-feed-rss-image'] ) ? 'hide' : 'show';
			}
		}

		return $defaults;
	}
}

if ( ! function_exists( 'rgcf_get_feed_url' ) ) {
	/**
	 * Build feed URL.
	 *
	 * @return string
	 */
	function rgcf_get_feed_url() {
		return add_query_arg( 'feed', 'recent-global-comments', home_url( '/' ) );
	}
}

if ( ! function_exists( 'rgcf_feed_extension_active' ) ) {
	/**
	 * Check whether the comments feed extension is active and usable.
	 *
	 * @return bool
	 */
	function rgcf_feed_extension_active() {
		$comment_indexer_active = function_exists( 'get_site_option' ) && get_site_option( 'comment_indexer_active', 0 );
		if ( ! $comment_indexer_active ) {
			return false;
		}

		if ( function_exists( 'ps_postindexer_is_extension_enabled' ) ) {
			return ps_postindexer_is_extension_enabled( 'recent_global_comments_feed' );
		}

		return false;
	}
}

if ( ! function_exists( 'rgcf_fetch_recent_comments' ) ) {
	/**
	 * Fetch recent network comments for the feed.
	 *
	 * @param int $number Number of comments.
	 * @return array
	 */
	function rgcf_fetch_recent_comments( $number ) {
		global $wpdb;

		$number = absint( $number );
		if ( ! $number ) {
			$number = 25;
		}

		$current_network = function_exists( 'get_current_network' ) ? get_current_network() : null;
		$site_id = $current_network && isset( $current_network->id ) ? (int) $current_network->id : 1;

		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->base_prefix}site_comments WHERE site_id = %d AND blog_public = '1' AND comment_approved = '1' AND comment_type != 'pingback' ORDER BY comment_date_stamp DESC LIMIT %d",
			$site_id,
			$number
		);

		$comments = $wpdb->get_results( $query, ARRAY_A );
		return is_array( $comments ) ? $comments : array();
	}
}

if ( ! function_exists( 'recent_global_comments_feed' ) ) {
	/**
	 * RSS callback for recent global comments.
	 */
	function recent_global_comments_feed() {
		if ( ! rgcf_feed_extension_active() ) {
			status_header( 404 );
			exit;
		}

		global $wpdb;
		$number = isset( $_GET['number'] ) ? absint( $_GET['number'] ) : 25;
		$comments = rgcf_fetch_recent_comments( $number );

		if ( count( $comments ) > 0 ) {
			$first = reset( $comments );
			$last_published = isset( $first['comment_date_gmt'] ) ? $first['comment_date_gmt'] : current_time( 'mysql', true );
		} else {
			$last_published = current_time( 'mysql', true );
		}

		header( 'HTTP/1.0 200 OK' );
		header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );

		echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>';
		?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	<?php do_action( 'rss2_ns' ); ?>
>
<channel>
	<title><?php bloginfo_rss( 'name' ); ?> <?php esc_html_e( 'Kommentare', 'postindexer' ); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php bloginfo_rss( 'url' ); ?></link>
	<description><?php bloginfo_rss( 'description' ); ?></description>
	<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', $last_published, false ) ); ?></pubDate>
	<?php the_generator( 'rss2' ); ?>
	<language><?php bloginfo_rss( 'language' ); ?></language>
	<?php foreach ( $comments as $comment ) : ?>
		<?php
		$post_title = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_title FROM {$wpdb->base_prefix}" . (int) $comment['blog_id'] . "_posts WHERE ID = %d",
				(int) $comment['comment_post_id']
			)
		);
		?>
		<item>
			<title><?php esc_html_e( 'Kommentar zu', 'postindexer' ); ?>: <?php echo esc_html( wp_strip_all_tags( (string) $post_title ) ); ?></title>
			<link><?php echo esc_url( $comment['comment_post_permalink'] ); ?>#comment-<?php echo (int) $comment['comment_id']; ?></link>
			<dc:creator><?php echo esc_html( $comment['comment_author'] ); ?></dc:creator>
			<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', $comment['comment_date_gmt'], false ) ); ?></pubDate>
			<guid isPermaLink="false"><?php echo esc_url( $comment['comment_post_permalink'] ); ?>#comment-<?php echo (int) $comment['comment_id']; ?></guid>
			<description><![CDATA[<?php echo wp_kses_post( wp_strip_all_tags( (string) $comment['comment_content'] ) ); ?>]]></description>
		</item>
	<?php endforeach; ?>
</channel>
</rss>
		<?php
		exit;
	}
}

if ( ! function_exists( 'recent_global_comments_feed_rewrite' ) ) {
	/**
	 * Add generic xml feed rewrite fallback.
	 *
	 * @param WP_Rewrite $wp_rewrite Rewrite object.
	 */
	function recent_global_comments_feed_rewrite( $wp_rewrite ) {
		$feed_rules = array(
			'feed/(.+)' => 'index.php?feed=' . $wp_rewrite->preg_index( 1 ),
			'(.+).xml' => 'index.php?feed=' . $wp_rewrite->preg_index( 1 ),
		);
		$wp_rewrite->rules = $feed_rules + $wp_rewrite->rules;
	}
}

if ( ! function_exists( 'rgcf_register_widget' ) ) {
	/**
	 * Register comments feed widget.
	 */
	function rgcf_register_widget() {
		register_widget( Recent_Global_Comments_Feed_Widget::NAME );
	}
}

if ( ! function_exists( 'widget_recent_global_comments_feed_init' ) ) {
	/**
	 * Backward-compatible wrapper for legacy bootstrap.
	 */
	function widget_recent_global_comments_feed_init() {
		rgcf_register_widget();
	}
}

if ( ! function_exists( 'rgcf_register_widget_if_active' ) ) {
	/**
	 * Register widget only when extension is active for current site.
	 */
	function rgcf_register_widget_if_active() {
		if ( rgcf_feed_extension_active() ) {
			rgcf_register_widget();
		}
	}
}
add_action( 'widgets_init', 'rgcf_register_widget_if_active' );

if ( ! function_exists( 'rgcf_register_feed_hooks' ) ) {
	/**
	 * Register feed hooks only when extension is active.
	 */
	function rgcf_register_feed_hooks() {
		if ( ! rgcf_feed_extension_active() ) {
			return;
		}

		add_filter( 'generate_rewrite_rules', 'recent_global_comments_feed_rewrite' );
		add_action( 'do_feed_recent-global-comments', 'recent_global_comments_feed' );
	}
}
add_action( 'init', 'rgcf_register_feed_hooks', 20 );
