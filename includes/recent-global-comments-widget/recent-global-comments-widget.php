<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recent_Global_Comments_Widget extends WP_Widget {

	const NAME = __CLASS__;
	const TEXT_DOMAIN = 'postindexer';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'recent_global_comments_widget',
			__( 'Globale Kommentare', self::TEXT_DOMAIN ),
			array(
				'classname' => 'recent_global_comments_widget',
				'description' => __( 'Zeigt die neuesten Kommentare aus dem Netzwerk an.', self::TEXT_DOMAIN ),
			)
		);
	}

	/**
	 * Widget output.
	 *
	 * @param array $args     Widget args.
	 * @param array $instance Saved instance.
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, self::get_defaults() );

		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$number = absint( $instance['number'] );
		if ( ! $number ) {
			$number = 10;
		}

		$content_characters = absint( $instance['content_characters'] );
		if ( ! $content_characters ) {
			$content_characters = 50;
		}

		$avatars = ( isset( $instance['avatars'] ) && 'hide' === $instance['avatars'] ) ? 'hide' : 'show';
		$avatar_size = absint( $instance['avatar_size'] );
		if ( ! $avatar_size ) {
			$avatar_size = 32;
		}

		$comments = rgcwidget_get_recent_comments( $number );

		echo $args['before_widget'];
		if ( '' !== trim( (string) $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		if ( ! empty( $comments ) ) {
			echo '<ul>';
			foreach ( $comments as $comment ) {
				echo '<li>';

				if ( 'show' === $avatars ) {
					$id_or_email = ! empty( $comment['comment_author_user_id'] )
						? (int) $comment['comment_author_user_id']
						: (string) $comment['comment_author_email'];
					echo '<a href="' . esc_url( $comment['comment_post_permalink'] ) . '">';
					echo get_avatar( $id_or_email, $avatar_size, '' );
					echo '</a> ';
				}

				$content = wp_strip_all_tags( (string) $comment['comment_content'] );
				$excerpt = rgcwidget_substr( $content, 0, $content_characters );
				echo esc_html( $excerpt );
				echo ' (<a href="' . esc_url( $comment['comment_post_permalink'] ) . '#comment-' . (int) $comment['comment_id'] . '">';
				echo esc_html__( 'Mehr', self::TEXT_DOMAIN );
				echo '</a>)';
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'Keine Kommentare gefunden.', self::TEXT_DOMAIN ) . '</p>';
		}

		echo $args['after_widget'];
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Saved instance.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, self::get_defaults() );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Titel', self::TEXT_DOMAIN ); ?>:</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" type="text">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Anzahl', self::TEXT_DOMAIN ); ?>:</label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" class="widefat">
				<?php for ( $i = 1; $i <= 25; $i++ ) : ?>
					<option value="<?php echo (int) $i; ?>" <?php selected( (int) $instance['number'], $i ); ?>><?php echo (int) $i; ?></option>
				<?php endfor; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'content_characters' ) ); ?>"><?php esc_html_e( 'Zeichen im Kommentar', self::TEXT_DOMAIN ); ?>:</label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'content_characters' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'content_characters' ) ); ?>" class="widefat">
				<?php for ( $i = 1; $i <= 500; $i++ ) : ?>
					<option value="<?php echo (int) $i; ?>" <?php selected( (int) $instance['content_characters'], $i ); ?>><?php echo (int) $i; ?></option>
				<?php endfor; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'avatars' ) ); ?>"><?php esc_html_e( 'Avatare', self::TEXT_DOMAIN ); ?>:</label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'avatars' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'avatars' ) ); ?>" class="widefat">
				<option value="show" <?php selected( $instance['avatars'], 'show' ); ?>><?php esc_html_e( 'Anzeigen', self::TEXT_DOMAIN ); ?></option>
				<option value="hide" <?php selected( $instance['avatars'], 'hide' ); ?>><?php esc_html_e( 'Ausblenden', self::TEXT_DOMAIN ); ?></option>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'avatar_size' ) ); ?>"><?php esc_html_e( 'Avatar-Groesse', self::TEXT_DOMAIN ); ?>:</label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'avatar_size' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'avatar_size' ) ); ?>" class="widefat">
				<?php foreach ( array( 16, 32, 48, 96, 128 ) as $size ) : ?>
					<option value="<?php echo (int) $size; ?>" <?php selected( (int) $instance['avatar_size'], (int) $size ); ?>><?php echo (int) $size; ?>px</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Update widget settings.
	 *
	 * @param array $new_instance New values.
	 * @param array $old_instance Old values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = self::get_defaults();

		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['number'] = isset( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 10;
		$instance['content_characters'] = isset( $new_instance['content_characters'] ) ? absint( $new_instance['content_characters'] ) : 50;
		$instance['avatars'] = ( isset( $new_instance['avatars'] ) && 'hide' === $new_instance['avatars'] ) ? 'hide' : 'show';
		$instance['avatar_size'] = isset( $new_instance['avatar_size'] ) ? absint( $new_instance['avatar_size'] ) : 32;

		if ( ! $instance['number'] ) {
			$instance['number'] = 10;
		}
		if ( ! $instance['content_characters'] ) {
			$instance['content_characters'] = 50;
		}
		if ( ! $instance['avatar_size'] ) {
			$instance['avatar_size'] = 32;
		}

		return $instance;
	}

	/**
	 * Default widget values.
	 *
	 * @return array
	 */
	private static function get_defaults() {
		return array(
			'title' => '',
			'number' => 10,
			'content_characters' => 50,
			'avatars' => 'show',
			'avatar_size' => 32,
		);
	}
}

if ( ! function_exists( 'rgcwidget_substr' ) ) {
	/**
	 * mb_substr fallback wrapper.
	 *
	 * @param string $text   Text.
	 * @param int    $start  Start position.
	 * @param int    $length Length.
	 * @return string
	 */
	function rgcwidget_substr( $text, $start, $length ) {
		$fn = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
		return (string) $fn( (string) $text, (int) $start, (int) $length );
	}
}

if ( ! function_exists( 'rgcwidget_get_recent_comments' ) ) {
	/**
	 * Fetch recent approved network comments from the indexed table.
	 *
	 * @param int $limit Number of comments.
	 * @return array
	 */
	function rgcwidget_get_recent_comments( $limit = 10 ) {
		global $wpdb;

		$limit = absint( $limit );
		if ( ! $limit ) {
			$limit = 10;
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->base_prefix}site_comments WHERE blog_public = '1' AND comment_approved = '1' AND comment_type != 'pingback' ORDER BY comment_date_stamp DESC LIMIT %d",
			$limit
		);

		$comments = $wpdb->get_results( $query, ARRAY_A );
		return is_array( $comments ) ? $comments : array();
	}
}

if ( ! function_exists( 'rgcwidget_register_widget' ) ) {
	/**
	 * Register the modern comments widget.
	 */
	function rgcwidget_register_widget() {
		register_widget( Recent_Global_Comments_Widget::NAME );
	}
}

if ( ! function_exists( 'widget_recent_global_comments_init' ) ) {
	/**
	 * Backward-compatible wrapper for legacy loader calls.
	 */
	function widget_recent_global_comments_init() {
		rgcwidget_register_widget();
	}
}

if ( ! function_exists( 'rgcwidget_register_shortcode' ) ) {
	/**
	 * Register shortcode when extension is active for the current site.
	 */
	function rgcwidget_register_shortcode() {
		if ( ! function_exists( 'ps_postindexer_is_extension_enabled' ) || ! ps_postindexer_is_extension_enabled( 'recent_global_comments_widget' ) ) {
			return;
		}

		add_shortcode( 'network_comments', 'rgcwidget_render_shortcode' );
	}
}
add_action( 'plugins_loaded', 'rgcwidget_register_shortcode' );

if ( ! function_exists( 'rgcwidget_render_shortcode' ) ) {
	/**
	 * Render network comments shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	function rgcwidget_render_shortcode( $atts = array() ) {
		$defaults = array(
			'title' => '',
			'number' => 10,
			'content_characters' => 50,
			'avatars' => 'show',
			'avatar_size' => 32,
			'global_before' => '',
			'global_after' => '',
			'before' => '',
			'after' => '',
			'link' => '',
		);

		$settings = get_site_option( 'recent_global_comments_settings', array() );
		$settings = is_array( $settings ) ? array_merge( $defaults, $settings ) : $defaults;
		$atts = shortcode_atts( $settings, $atts );

		$number = absint( $atts['number'] );
		if ( ! $number ) {
			$number = 10;
		}

		$content_characters = absint( $atts['content_characters'] );
		if ( ! $content_characters ) {
			$content_characters = 50;
		}

		$avatar_size = absint( $atts['avatar_size'] );
		if ( ! $avatar_size ) {
			$avatar_size = 32;
		}

		$comments = rgcwidget_get_recent_comments( $number );
		$html = (string) $atts['global_before'];

		if ( ! empty( $atts['title'] ) ) {
			$html .= '<h3>' . esc_html( $atts['title'] ) . '</h3>';
		}

		if ( ! empty( $comments ) ) {
			$html .= '<ul class="network-comments-list">';
			foreach ( $comments as $comment ) {
				$html .= (string) $atts['before'] . '<li>';
				if ( 'show' === $atts['avatars'] ) {
					$id_or_email = ! empty( $comment['comment_author_user_id'] ) ? (int) $comment['comment_author_user_id'] : (string) $comment['comment_author_email'];
					$html .= '<a href="' . esc_url( $comment['comment_post_permalink'] ) . '">' . get_avatar( $id_or_email, $avatar_size, '' ) . '</a> ';
				}

				$content = wp_strip_all_tags( (string) $comment['comment_content'] );
				$excerpt = rgcwidget_substr( $content, 0, $content_characters );
				if ( strlen( $content ) > $content_characters ) {
					$excerpt .= '...';
				}
				$html .= esc_html( $excerpt );

				$link_text = '' !== (string) $atts['link'] ? (string) $atts['link'] : __( 'Mehr', 'postindexer' );
				$html .= ' (<a href="' . esc_url( $comment['comment_post_permalink'] ) . '#comment-' . (int) $comment['comment_id'] . '">' . esc_html( $link_text ) . '</a>)';
				$html .= '</li>' . (string) $atts['after'];
			}
			$html .= '</ul>';
		} else {
			$html .= '<p>' . esc_html__( 'Keine Kommentare gefunden.', 'postindexer' ) . '</p>';
		}

		$html .= (string) $atts['global_after'];
		return $html;
	}
}
