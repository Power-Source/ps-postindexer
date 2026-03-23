<?php

class Global_Site_Search_Widget extends WP_Widget {

	public function __construct() {
		$widget_options = array(
			'classname'   => 'global-site-search',
			'description' => __( 'Netzwerksuche Widget', 'postindexer' ),
		);

		$control_options = array(
			'id_base' => 'global-site-search-widget',
		);

		parent::__construct( 'global-site-search-widget', __( 'Netzwerksuche Widget', 'postindexer' ), $widget_options, $control_options );
	}

	function widget( $args, $instance ) {
		global $global_site_search, $wp_query, $wpdb;

		extract( $args );

		echo $before_widget;

		$title = apply_filters( 'widget_title', $instance['title'] );
		if ( !empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}

		// Suchformular
		$phrase = isset($_GET['gss_widget_phrase']) ? trim(stripslashes($_GET['gss_widget_phrase'])) : '';
		// Widget-Suchformular per AJAX, damit kein Redirect erfolgt
		echo '<form id="gss-widget-form-' . esc_attr($this->id) . '" action="#" method="get">';
		echo '<input type="text" name="gss_widget_phrase" value="' . esc_attr($phrase) . '" placeholder="' . esc_attr__('Suchbegriff...', 'postindexer') . '" style="width:70%;margin-right:0.5em;">';
		echo '<input type="submit" value="' . esc_attr__('Suchen', 'postindexer') . '">';
		echo '</form>';
		echo '<div id="gss-widget-results-' . esc_attr($this->id) . '">';
		if ($phrase !== '') {
			$limit = 5;
			$post_type = get_site_option('global_site_search_post_type', 'post');
			$prepared = global_site_search_prepare_ajax_query( $phrase, $post_type, $limit, 1 );
			$results = ! empty( $prepared['query'] ) ? $wpdb->get_results( $prepared['query'] ) : array();
			echo global_site_search_render_ajax_results( $results, $phrase, 1, $limit, __( 'Keine Treffer gefunden.', 'postindexer' ) );
		}
		echo '</div>';
		// AJAX-Handler für das Widget
		echo '<script>document.addEventListener("DOMContentLoaded",function(){
            var form=document.getElementById("gss-widget-form-' . esc_attr($this->id) . '");
			var input=form?form.querySelector("input[name=gss_widget_phrase]"):null;
            var results=document.getElementById("gss-widget-results-' . esc_attr($this->id) . '");
			var observer=null;
			function bindObserver(){
				if(observer){observer.disconnect();}
				var button=results?results.querySelector(".gss-load-more"):null;
				if(!button||!("IntersectionObserver" in window)){return;}
				observer=new IntersectionObserver(function(entries){
					entries.forEach(function(entry){
						if(entry.isIntersecting){
							observer.disconnect();
							button.click();
						}
					});
				},{rootMargin:"180px 0px"});
				observer.observe(button);
			}
			function mergeResults(html){
				var temp=document.createElement("div");
				temp.innerHTML=html;
				var incomingList=temp.querySelector(".gss-results-list");
				var currentList=results.querySelector(".gss-results-list");
				var currentMore=results.querySelector(".gss-results-more");
				var incomingMore=temp.querySelector(".gss-results-more");
				if(!currentList||!incomingList){
					results.innerHTML=html;
					bindObserver();
					return;
				}
				while(incomingList.firstChild){
					currentList.appendChild(incomingList.firstChild);
				}
				if(currentMore){currentMore.remove();}
				if(incomingMore){results.appendChild(incomingMore);}
				bindObserver();
			}
			function fetchResults(page,append){
				if(!form||!input||!results){return;}
				var phrase=input.value.trim();
				if(!phrase){results.innerHTML="";return;}
				if(!append){
					results.innerHTML=\'<div class="gss-search-loading">Suche läuft...</div>\';
				}
				var params=new URLSearchParams();
				params.set("gss_widget_ajax","1");
				params.set("phrase",phrase);
				params.set("page",String(page));
				fetch(window.location.pathname+"?"+params.toString())
					.then(function(r){return r.text();})
					.then(function(html){
						if(append){mergeResults(html);}else{results.innerHTML=html;bindObserver();}
					});
			}
			if(form){
				form.addEventListener("submit",function(e){
					e.preventDefault();
					fetchResults(1,false);
				});
			}
			if(results){
				results.addEventListener("click",function(e){
					var button=e.target.closest(".gss-load-more");
					if(!button){return;}
					e.preventDefault();
					if(button.disabled){return;}
					button.disabled=true;
					button.textContent="Lade mehr...";
					fetchResults(parseInt(button.getAttribute("data-next-page")||"2",10),true);
				});
				bindObserver();
			}
        });</script>';

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags for title and name to remove HTML (important for text inputs). */
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array)$instance, array(
			'title' => __( 'Netzwerksuche', 'postindexer' ),
		) );
		?><p>
			<label for="<?php echo $this->get_field_id( 'title' ) ?>"><?php _e( 'Titel', 'postindexer' ) ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id( 'title' ) ?>" name="<?php echo $this->get_field_name( 'title' ) ?>" value="<?php echo esc_attr( $instance['title'] ) ?>" class="widefat">
		</p><?php
	}

}

// Integration als Erweiterung für den Beitragsindexer
add_action('plugins_loaded', function() {
	if ( !class_exists('Postindexer_Extensions_Admin') ) return;
	global $postindexer_extensions_admin;
	if ( !isset($postindexer_extensions_admin) ) {
		if ( isset($GLOBALS['postindexeradmin']) && isset($GLOBALS['postindexeradmin']->extensions_admin) ) {
			$postindexer_extensions_admin = $GLOBALS['postindexeradmin']->extensions_admin;
		}
	}
	if ( isset($postindexer_extensions_admin) && $postindexer_extensions_admin->is_extension_active_for_site('global_site_search') ) {
		add_action( 'widgets_init', 'global_site_search_load_widgets' );
	}
});

// Entfernt: add_action( 'widgets_init', 'global_site_search_load_widgets' );
function global_site_search_load_widgets() {
	if ( in_array( get_current_blog_id(), global_site_search_get_allowed_blogs() ) ) {
		register_widget( 'Global_Site_Search_Widget' );
	}
}