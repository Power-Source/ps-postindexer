<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rgpf_feed_extension_active' ) ) {
	/**
	 * Check whether global posts feed extension is active for current site.
	 *
	 * @return bool
	 */
	function rgpf_feed_extension_active() {
		if ( function_exists( 'ps_postindexer_is_extension_enabled' ) ) {
			return ps_postindexer_is_extension_enabled( 'recent_global_posts_feed' );
		}
		return false;
	}
}

if ( ! function_exists( 'rgpf_register_feed' ) ) {
	/**
	 * Register the feed endpoint.
	 */
	function rgpf_register_feed() {
		add_feed( 'globalpostsfeed', 'rgpf_render_feed' );
	}
}

if ( ! function_exists( 'rgpf_register_feed_rewrite' ) ) {
	/**
	 * Add fallback rewrite rules for feed urls.
	 *
	 * @param WP_Rewrite $wp_rewrite Rewrite object.
	 */
	function rgpf_register_feed_rewrite( $wp_rewrite ) {
		$feed_rules = array(
			'feed/(.+)' => 'index.php?feed=' . $wp_rewrite->preg_index( 1 ),
			'(.+).xml' => 'index.php?feed=' . $wp_rewrite->preg_index( 1 ),
		);
		$wp_rewrite->rules = $feed_rules + $wp_rewrite->rules;
	}
}

if ( ! function_exists( 'rgpf_render_feed' ) ) {
	/**
	 * Render recent global posts feed.
	 */
	function rgpf_render_feed() {
		global $network_post;

		if ( ! rgpf_feed_extension_active() ) {
			status_header( 404 );
			exit;
		}

		if ( ! function_exists( 'network_query_posts' ) ) {
			status_header( 500 );
			exit;
		}

		$post_type = isset( $_GET['posttype'] ) ? sanitize_key( wp_unslash( $_GET['posttype'] ) ) : 'post';
		if ( '' === $post_type ) {
			$post_type = 'post';
		}

		$number = isset( $_GET['number'] ) ? absint( $_GET['number'] ) : 25;
		if ( ! $number ) {
			$number = 25;
		}

		network_query_posts(
			array(
				'post_type' => $post_type,
				'posts_per_page' => $number,
			)
		);

		remove_all_filters( 'excerpt_more' );

		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );
		}

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
	<title><?php bloginfo_rss( 'name' ); ?> - <?php esc_html_e( 'Neueste Netzwerkbeitraege', 'postindexer' ); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php bloginfo_rss( 'url' ); ?></link>
	<description><?php bloginfo_rss( 'description' ); ?></description>
	<lastBuildDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', network_get_lastpostmodified( 'GMT' ), false ) ); ?></lastBuildDate>
	<language><?php bloginfo_rss( 'language' ); ?></language>
	<sy:updatePeriod><?php echo esc_html( apply_filters( 'rss_update_period', 'hourly' ) ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo esc_html( apply_filters( 'rss_update_frequency', '1' ) ); ?></sy:updateFrequency>
	<?php do_action( 'rss2_head' ); ?>
	<?php while ( network_have_posts() ) : network_the_post(); ?>
		<item>
			<title><![CDATA[<?php network_the_title_rss(); ?>]]></title>
			<link><?php network_the_permalink_rss(); ?></link>
			<comments><?php network_comments_link_feed(); ?></comments>
			<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', network_get_post_time( 'Y-m-d H:i:s', true ), false ) ); ?></pubDate>
			<dc:creator><![CDATA[<?php network_the_author(); ?>]]></dc:creator>
			<?php network_the_category_rss( 'rss2' ); ?>
			<guid isPermaLink="false"><?php network_the_guid(); ?></guid>
			<?php if ( get_option( 'rss_use_excerpt' ) ) : ?>
				<description><![CDATA[<?php network_the_excerpt_rss(); ?>]]></description>
			<?php else : ?>
				<description><![CDATA[<?php network_the_excerpt_rss(); ?>]]></description>
				<?php if ( isset( $network_post->post_content ) && strlen( $network_post->post_content ) > 0 ) : ?>
					<content:encoded><![CDATA[<?php network_the_content_feed( 'rss2' ); ?>]]></content:encoded>
				<?php else : ?>
					<content:encoded><![CDATA[<?php network_the_excerpt_rss(); ?>]]></content:encoded>
				<?php endif; ?>
			<?php endif; ?>
			<wfw:commentRss><?php echo esc_url( network_get_post_comments_feed_link( null, 'rss2' ) ); ?></wfw:commentRss>
			<slash:comments><?php echo (int) network_get_comments_number(); ?></slash:comments>
			<?php network_rss_enclosure(); ?>
			<?php do_action( 'network_rss2_item' ); ?>
		</item>
	<?php endwhile; ?>
</channel>
</rss>
		<?php
		exit;
	}
}

if ( ! function_exists( 'rgpf_bootstrap_feed' ) ) {
	/**
	 * Register hooks for feed endpoint only when extension is active.
	 */
	function rgpf_bootstrap_feed() {
		if ( ! rgpf_feed_extension_active() ) {
			return;
		}

		rgpf_register_feed();
		add_filter( 'generate_rewrite_rules', 'rgpf_register_feed_rewrite' );
	}
}
add_action( 'init', 'rgpf_bootstrap_feed', 20 );
