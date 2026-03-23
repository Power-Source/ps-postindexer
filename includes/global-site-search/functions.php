<?php

function global_site_search_roundup( $value, $dp ) {
	return ceil( $value * pow( 10, $dp ) ) / pow( 10, $dp );
}

function global_site_search_get_allowed_blogs() {
	if ( !defined( 'GLOBAL_SITE_SEARCH_BLOG' ) ) {
		define( 'GLOBAL_SITE_SEARCH_BLOG', 1 );
	}

	$site_search_blog = GLOBAL_SITE_SEARCH_BLOG;
	if ( is_string( $site_search_blog ) ) {
		$site_search_blog = array_filter( array_map( 'absint', explode( ',', $site_search_blog ) ) );
	}

	return apply_filters( 'global_site_search_allowed_blogs', (array)$site_search_blog );
}

function global_site_search_locate_template( $template_name, $template_path = 'global-site-search' ) {
	// Look within passed path within the theme - this is priority
	$template = locate_template( array(
		trailingslashit( $template_path ) . $template_name,
		$template_name
	) );

	// Get default template
	if ( !$template ) {
		$template = implode( DIRECTORY_SEPARATOR, array( dirname( __FILE__ ), 'templates', $template_name ) );
	}

	// Return what we found
	return apply_filters( 'global_site_search_locate_template', $template, $template_name, $template_path );
}

function global_site_search_form() {
	include global_site_search_locate_template( 'global-site-search-form.php' );
}

function global_site_search_get_search_base() {
	global $global_site_search;
	return $global_site_search->global_site_search_base;
}

function global_site_search_get_phrase() {
	global $wp_query;

	$phrase = isset( $wp_query->query_vars['search'] ) ? urldecode( $wp_query->query_vars['search'] ) : '';
	if ( empty( $phrase ) && isset( $_REQUEST['phrase'] ) ) {
		$phrase = trim( stripslashes($_REQUEST['phrase'] ));
	}
	return $phrase;
}

function global_site_search_get_pagination( $mainlink = '' ) {
	global $network_query, $current_site;
	if ( absint( $network_query->max_num_pages ) <= 1 ) {
		return '';
	}

	if ( empty( $mainlink ) ) {
		$mainlink = $current_site->path . global_site_search_get_search_base() . '/' . urlencode( global_site_search_get_phrase() );
	}

	return paginate_links( array(
		'base'      => trailingslashit( $mainlink ) . '%_%',
		'format'    => 'page/%#%',
		'total'     => $network_query->max_num_pages,
		'current'   => !empty( $network_query->query_vars['paged'] ) ? $network_query->query_vars['paged'] : 1,
		'prev_next' => true,
	) );
}

function global_site_search_get_background_color() {
	return get_site_option( 'global_site_search_background_color', '#F2F2EA' );
}

function global_site_search_get_alt_background_color() {
	return get_site_option( 'global_site_search_alternate_background_color', '#FFFFFF' );
}

function global_site_search_get_border_color() {
	return get_site_option( 'global_site_search_border_color', '#CFD0CB' );
}

function global_site_search_get_search_terms( $phrase ) {
	$phrase = trim( wp_strip_all_tags( (string) $phrase ) );
	if ( '' === $phrase ) {
		return array();
	}

	$normalized = preg_replace( '/[\p{Pd}_]+/u', ' ', $phrase );
	$normalized = preg_replace( '/\s+/u', ' ', (string) $normalized );
	$parts = preg_split( "/[\\s,.;:!?()\\[\\]{}\"'\/\\\\|#+*=]+/u", $normalized, -1, PREG_SPLIT_NO_EMPTY );
	$terms = array();

	foreach ( (array) $parts as $part ) {
		$part = trim( $part );
		if ( '' === $part ) {
			continue;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $part ) : strlen( $part );
		if ( $length >= 2 || preg_match( '/\d/u', $part ) ) {
			$terms[] = $part;
		}
	}

	if ( empty( $terms ) ) {
		$terms[] = $phrase;
	}

	return array_values( array_unique( $terms ) );
}

function global_site_search_prepare_ajax_query( $phrase, $post_type, $per_page, $page ) {
	global $wpdb;

	$terms = global_site_search_get_search_terms( $phrase );
	if ( empty( $terms ) ) {
		return array(
			'query' => '',
			'terms' => array(),
		);
	}

	$where_parts = array();
	$params = array();
	foreach ( $terms as $term ) {
		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$where_parts[] = '(post_title LIKE %s OR post_content LIKE %s)';
		$params[] = $like;
		$params[] = $like;
	}

	$offset = max( 0, ( (int) $page - 1 ) * (int) $per_page );
	$sql = "SELECT ID, BLOG_ID, post_title, post_content, guid, post_date FROM {$wpdb->base_prefix}network_posts WHERE " . implode( ' AND ', $where_parts ) . " AND post_status = 'publish'";

	if ( 'all' !== $post_type ) {
		$sql .= ' AND post_type = %s';
		$params[] = $post_type;
	}

	$sql .= ' ORDER BY post_date DESC LIMIT %d OFFSET %d';
	$params[] = (int) $per_page + 1;
	$params[] = $offset;

	return array(
		'query' => call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) ),
		'terms' => $terms,
	);
}

function global_site_search_get_site_name( $blog_id ) {
	static $cache = array();

	$blog_id = (int) $blog_id;
	if ( isset( $cache[ $blog_id ] ) ) {
		return $cache[ $blog_id ];
	}

	$name = '';
	$details = get_blog_details( $blog_id );
	if ( $details && ! is_wp_error( $details ) ) {
		$name = (string) $details->blogname;
		if ( '' === $name && ! empty( $details->domain ) ) {
			$name = (string) $details->domain;
		}
	}

	if ( '' === $name ) {
		$name = (string) get_blog_option( $blog_id, 'blogname', '' );
	}

	if ( '' === $name ) {
		$name = (string) wp_parse_url( get_home_url( $blog_id ), PHP_URL_HOST );
	}

	$cache[ $blog_id ] = $name;
	return $name;
}

function global_site_search_format_result_url( $url ) {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) ) {
		return $url;
	}

	$label = $parts['host'];
	if ( ! empty( $parts['path'] ) && '/' !== $parts['path'] ) {
		$label .= untrailingslashit( $parts['path'] );
	}

	return $label;
}

function global_site_search_highlight_text( $text, $terms ) {
	$text = (string) $text;
	if ( '' === trim( $text ) ) {
		return '';
	}

	$terms = array_values( array_filter( array_map( 'trim', (array) $terms ) ) );
	if ( empty( $terms ) ) {
		return esc_html( $text );
	}

	usort(
		$terms,
		function ( $left, $right ) {
			return strlen( $right ) <=> strlen( $left );
		}
	);

	$quoted_terms = array();
	foreach ( $terms as $term ) {
		$quoted_terms[] = preg_quote( $term, '/' );
	}

	$pattern = '/(' . implode( '|', $quoted_terms ) . ')/iu';
	$parts = preg_split( $pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	if ( false === $parts ) {
		return esc_html( $text );
	}

	$output = '';
	foreach ( $parts as $part ) {
		if ( preg_match( $pattern, $part ) ) {
			$output .= '<mark class="gss-hit">' . esc_html( $part ) . '</mark>';
		} else {
			$output .= esc_html( $part );
		}
	}

	return $output;
}

function global_site_search_build_excerpt( $content, $terms, $max_length = 220 ) {
	$text = strip_shortcodes( (string) $content );
	$text = wp_strip_all_tags( $text );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = trim( (string) $text );

	if ( '' === $text ) {
		return '';
	}

	$text_length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	if ( $text_length <= $max_length ) {
		return global_site_search_highlight_text( $text, $terms );
	}

	$position = null;
	foreach ( (array) $terms as $term ) {
		$term_position = function_exists( 'mb_stripos' ) ? mb_stripos( $text, $term ) : stripos( $text, $term );
		if ( false !== $term_position && ( null === $position || $term_position < $position ) ) {
			$position = $term_position;
		}
	}

	$start = null === $position ? 0 : max( 0, $position - 70 );
	$excerpt = function_exists( 'mb_substr' ) ? mb_substr( $text, $start, $max_length ) : substr( $text, $start, $max_length );

	if ( $start > 0 ) {
		$excerpt = '...' . ltrim( $excerpt );
	}

	if ( $start + $max_length < $text_length ) {
		$excerpt = rtrim( $excerpt ) . '...';
	}

	return global_site_search_highlight_text( $excerpt, $terms );
}

function global_site_search_render_ajax_results( $results, $phrase, $page, $per_page, $empty_message = '' ) {
	$terms = global_site_search_get_search_terms( $phrase );
	$page = max( 1, (int) $page );
	$per_page = max( 1, (int) $per_page );
	$empty_message = '' !== $empty_message ? $empty_message : __( 'Keine Treffer gefunden.', 'postindexer' );

	$has_more = count( $results ) > $per_page;
	if ( $has_more ) {
		$results = array_slice( $results, 0, $per_page );
	}

	ob_start();
	if ( ! empty( $results ) ) {
		echo '<div class="gss-results-list">';
		foreach ( $results as $row ) {
			$blog_id = isset( $row->BLOG_ID ) ? (int) $row->BLOG_ID : 0;
			$site_name = global_site_search_get_site_name( $blog_id );
			$site_url = $blog_id ? get_home_url( $blog_id ) : '';
			$result_url = isset( $row->guid ) ? (string) $row->guid : '';
			$url_label = global_site_search_format_result_url( $result_url );
			$excerpt = global_site_search_build_excerpt( isset( $row->post_content ) ? $row->post_content : '', $terms );

			echo '<article class="gss-result-card">';
			echo '<div class="gss-result-topline">';
			if ( '' !== $site_url ) {
				echo '<a class="gss-result-site" href="' . esc_url( $site_url ) . '">' . esc_html( $site_name ) . '</a>';
			} else {
				echo '<span class="gss-result-site">' . esc_html( $site_name ) . '</span>';
			}
			if ( '' !== $result_url ) {
				echo '<a class="gss-result-url" href="' . esc_url( $result_url ) . '">' . esc_html( $url_label ) . '</a>';
			}
			echo '</div>';
			echo '<h3 class="gss-result-title"><a href="' . esc_url( $result_url ) . '">' . global_site_search_highlight_text( isset( $row->post_title ) ? $row->post_title : '', $terms ) . '</a></h3>';
			if ( '' !== $excerpt ) {
				echo '<p class="gss-result-excerpt">' . $excerpt . '</p>';
			}
			echo '</article>';
		}
		echo '</div>';

		if ( $has_more ) {
			echo '<div class="gss-results-more">';
			echo '<button type="button" class="gss-load-more" data-next-page="' . esc_attr( $page + 1 ) . '">' . esc_html__( 'Weitere Treffer laden', 'postindexer' ) . '</button>';
			echo '</div>';
		}
	} else {
		echo '<div class="gss-no-results">' . esc_html( $empty_message ) . '</div>';
	}

	return ob_get_clean();
}