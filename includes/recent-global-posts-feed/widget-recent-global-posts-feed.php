<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recent_Global_Posts_Feed_Widget extends WP_Widget {

	const NAME = __CLASS__;
	const TEXT_DOMAIN = 'postindexer';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'recent_global_posts_feed_widget',
			__( 'Neuester Netzwerkbeitraege-Feed', self::TEXT_DOMAIN ),
			array(
				'classname' => 'rgpwidget',
				'description' => __( 'Zeigt einen Link zum globalen Beitrags-Feed an.', self::TEXT_DOMAIN ),
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
		$post_type = sanitize_key( $instance['post_type'] );
		if ( '' === $post_type ) {
			$post_type = 'post';
		}

		$feed_url = add_query_arg(
			array(
				'feed' => 'globalpostsfeed',
				'posttype' => $post_type,
			),
			home_url( '/' )
		);

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
				<option value="hide" <?php selected( $instance['rss_image'], 'hide' ); ?>><?php esc_html_e( 'Verbergen', self::TEXT_DOMAIN ); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>"><?php esc_html_e( 'Beitragstyp', self::TEXT_DOMAIN ); ?>:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'post_type' ) ); ?>" value="<?php echo esc_attr( $instance['post_type'] ); ?>" type="text">
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
		$instance['post_type'] = isset( $new_instance['post_type'] ) ? sanitize_key( $new_instance['post_type'] ) : 'post';
		if ( '' === $instance['post_type'] ) {
			$instance['post_type'] = 'post';
		}
		return $instance;
	}

	/**
	 * Default values with legacy option fallback.
	 *
	 * @return array
	 */
	private static function get_default_instance() {
		$defaults = array(
			'title' => __( 'Neuester Netzwerkbeitraege-Feed', self::TEXT_DOMAIN ),
			'rss_image' => 'show',
			'post_type' => 'post',
		);

		$legacy = get_option( 'widget_recent_global_posts_feed', array() );
		if ( is_array( $legacy ) ) {
			if ( ! empty( $legacy['recentglobalpostsfeedtitle'] ) ) {
				$defaults['title'] = sanitize_text_field( $legacy['recentglobalpostsfeedtitle'] );
			}
			if ( ! empty( $legacy['recentglobalpostsfeedrssimage'] ) ) {
				$defaults['rss_image'] = ( 'hide' === $legacy['recentglobalpostsfeedrssimage'] ) ? 'hide' : 'show';
			}
			if ( ! empty( $legacy['recentglobalpostsfeedpoststype'] ) ) {
				$defaults['post_type'] = sanitize_key( $legacy['recentglobalpostsfeedpoststype'] );
			}
		}

		return $defaults;
	}
}

if ( ! function_exists( 'rgpfwidget_register_widget' ) ) {
	/**
	 * Register the posts feed widget.
	 */
	function rgpfwidget_register_widget() {
		register_widget( Recent_Global_Posts_Feed_Widget::NAME );
	}
}

if ( ! function_exists( 'widget_recent_global_posts_feed_register' ) ) {
	/**
	 * Backward-compatible wrapper for legacy bootstrap.
	 */
	function widget_recent_global_posts_feed_register() {
		rgpfwidget_register_widget();
	}
}
