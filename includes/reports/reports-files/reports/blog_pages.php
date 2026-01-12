<?php
require_once dirname( __DIR__ ) . '/report-graphs/open-flash-chart/open-flash-chart.php';

// Stelle sicher, dass Konstanten definiert sind
if( ! defined( 'REPORTS_PLUGIN_DIR' ) )
	define( 'REPORTS_PLUGIN_DIR', plugin_dir_path( dirname(__DIR__) ) . 'reports-files/' );

if( ! defined( 'REPORTS_PLUGIN_URL' ) )
	define( 'REPORTS_PLUGIN_URL', plugin_dir_url( dirname(__DIR__) ) . 'reports-files/' );

global $activity_reports;
if (!isset($activity_reports) || !is_object($activity_reports)) return;

$activity_reports->add_report( __( 'Blog Pages', 'reports' ), 'blog-pages', __( 'Displays pages activity for a blog', 'reports' ) );

if (
    (isset($_GET['report']) && $_GET['report'] === 'blog-pages') ||
    (isset($_POST['report']) && $_POST['report'] === 'blog-pages')
) {
    add_action('view_report', 'report_blog_pages_ouput');
}

function report_blog_pages_ouput(){
	global $wpdb, $current_site, $activity_reports;

	$action = isset( $_GET[ 'report-action' ] ) ? $_GET[ 'report-action' ] : '';
	switch( $action ) {
		//---------------------------------------------------//
		default:
			// Hole indexierte Blogs aus Data Source
			if (!$activity_reports || !is_object($activity_reports)) {
				echo '<div class="notice notice-error"><p>' . esc_html__('Reports-Module nicht initialisiert.', 'postindexer') . '</p></div>';
				return;
			}
			$data_source = $activity_reports->get_data_source();
			if (!$data_source || !is_object($data_source)) {
				echo '<div class="notice notice-error"><p>' . esc_html__('Data Source nicht verfügbar.', 'postindexer') . '</p></div>';
				return;
			}
			$indexed_blogs = $data_source->get_indexed_blogs();
			?>
			<form name="report" method="POST" action="?page=reports&action=view-report&report=blog-pages&report-action=view">
				<table class="form-table">
					<?php if ( is_multisite() ) { ?>
					<tr valign="top">
						<th scope="row"><?php _e( 'Blog', 'reports' ) ?></th>
						<td>
							<select name="blog_ID" id="blog_ID" style="width: 95%">
								<option value=""><?php _e( '-- Select Blog --', 'reports' ); ?></option>
								<?php foreach ( $indexed_blogs as $blog ) : 
									$blog_details = get_blog_details( $blog->blog_id, false );
									if ( $blog_details ) :
								?>
									<option value="<?php echo esc_attr( $blog->blog_id ); ?>">
										<?php echo esc_html( $blog_details->blogname ); ?> 
										(ID: <?php echo $blog->blog_id; ?>, <?php printf( __( '%d posts', 'reports' ), $blog->post_count ); ?>)
									</option>
								<?php 
									endif;
								endforeach; ?>
							</select>
							<br />
							<small><?php _e( 'Only blogs with indexed pages are shown', 'reports' ); ?></small>
						</td>
					</tr>
					<?php } else { ?>
						<input type="hidden" name="blog_ID" id="blog_ID" value="0" />
					<?php } ?>
					<tr valign="top">
						<th scope="row"><?php _e( 'Period', 'reports' ) ?></th>
						<td>
							<select name="period" id="period">
								<option value="15" ><?php _e( '15 Days', 'reports' ); ?></option>
								<option value="30" ><?php _e( '30 Days', 'reports' ); ?></option>
								<option value="45" ><?php _e( '45 Days', 'reports' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="Submit" class="button-primary" value="<?php _e( 'View', 'reports' ) ?>" />
				</p>
			</form>
			<?php
		break;
		//---------------------------------------------------//
		case 'view':
			$blog_id = absint( $_POST['blog_ID'] );
			$period = absint( $_POST['period'] );
			$blog = is_multisite() ? get_blog_details( $blog_id, false ) : true;
			if ( ! $blog ) {
				?>
                <p><?php _e( 'Blog not found.', 'reports' ); ?></p>
                <?php
			}
			if ( $blog ) {
				?>
                <p>
                    <ul>
                        <li><strong><?php _e( 'Blog', 'reports' ); ?></strong>: <?php echo $blog_id; ?> (<?php echo get_site_url( $blog_id ); ?>)</li>
                        <li><strong><?php _e( 'Period', 'reports' ); ?></strong>: <?php printf( __( '%d Days', 'reports' ), $period ); ?></li>
                    </ul>
                </p>
                <?php
				//=======================================//
				// Nutze die neue Data Source aus dem Plugin-Core
				$data_source = $activity_reports->get_data_source();
				if (!$data_source || !is_object($data_source)) {
					echo '<div class="notice notice-error"><p>' . esc_html__('Data Source nicht verfügbar.', 'postindexer') . '</p></div>';
					break;
				}
				$total_days = $period;
				$date_format = get_option('date_format');

				// Hole Daten direkt aus dem indexierten Post Index
				$counts_by_date = $data_source->get_blog_pages_by_date( $blog_id, $period );

				// === ZUSAMMENFASSUNG ===
				$total_pages = array_sum( $counts_by_date );
				echo '<div style="background: #f8f9fa; border-left: 4px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 4px;">';
				echo '<h3 style="margin-top: 0; color: #0073aa;">' . esc_html__('Bericht: Blog Seiten', 'postindexer') . '</h3>';
				echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
				
				echo '<div>';
				echo '<div style="font-size: 28px; font-weight: bold; color: #0073aa;">' . intval($total_pages) . '</div>';
				echo '<div style="color: #666; font-size: 13px;">' . esc_html__('neue/bearbeitete Seiten in den letzten ' . intval($period) . ' Tagen', 'postindexer') . '</div>';
				echo '</div>';
				
				if ( !empty($counts_by_date) ) {
					$latest_day = max( array_keys($counts_by_date) );
					$latest_count = $counts_by_date[$latest_day];
					echo '<div>';
					echo '<div style="font-size: 28px; font-weight: bold; color: #28a745;">' . intval($latest_count) . '</div>';
					echo '<div style="color: #666; font-size: 13px;">' . esc_html__('Seiten am neuesten Datum (' . date_i18n('d. M Y', strtotime($latest_day)) . ')', 'postindexer') . '</div>';
					echo '</div>';
				}
				
				echo '</div>';
				echo '</div>';

				$report_data = array();
				$days = 0;
				while ( $days <= $total_days ) {
					$day = reports_days_ago($days,'Y-m-d');
					$label = reports_days_ago($days,$date_format);
					$value = isset($counts_by_date[$day]) ? $counts_by_date[$day] : 0;
					$report_data[] = array($label, $value);
					$days++;
				}

				$report_data = array_reverse($report_data);

				$count = 0;
				$array_labels = array();
				$array_values = array();
				$piwik_api_response = array();
				$piwik_api_response[] = array('1','2');
				foreach ( $report_data as $array_item ) {
					$count = $count + 1;
					if ( $count != 1 ) {
						$array_labels[] = $array_item[0];
						$array_values[] = $array_item[1];
					}
				}
				$label_count = count( $array_labels );
				$highest_value = 0;
				foreach ( $array_values as $value ) {
					if ( $value > $highest_value) {
						$highest_value = $value;
					}
				}
				
				// === GRAPH ===
				if ( !empty($array_values) && $highest_value > 0 ) {
					echo '<div style="background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 4px; margin-top: 20px;">';
					echo '<h4>' . esc_html__('Grafik: Seiten über Zeit', 'postindexer') . '</h4>';
					
					$g = new graph();
					//------------------------------//
					//---Data-----------------------//
					//------------------------------//
					$g->set_data( $array_values );

					//------------------------------//
					//---X--------------------------//
					//------------------------------//
					$g->set_x_labels( $array_labels );
					//------------------------------//
					//---Y--------------------------//
					//------------------------------//
					$g->set_y_min( 0 );
					$g->set_y_max( $highest_value );
					//------------------------------//
					$g->set_num_decimals ( 0 );
					$g->set_is_decimal_separator_comma( false );
					$g->set_is_thousand_separator_disabled( true );
					$g->y_axis_colour = '#ffffff';
					$g->x_axis_colour = '#596171';
					$g->x_grid_colour = $g->y_grid_colour = '#E0E1E4';

					// approx 5 x labels on the graph
					$steps = ceil($label_count / 5);
					$steps = $steps + $steps % 2; // make sure modulo 2

					$g->set_x_label_style( 10, $g->x_axis_colour, 0, $steps, $g->x_grid_colour );
					$g->set_x_axis_steps( $steps / 2 );


					$stepsY = ceil($highest_value / 4);
					$g->y_label_steps( $stepsY / 3 );
					$g->y_label_steps( 4 );

					$g->bg_colour = '#ffffff';
					$g->set_inner_background('#ffffff');
					$g->area_hollow( 1, 3, 4, '#3357A0', __( 'page(s)', 'reports' ), 10 );

					$g->set_tool_tip( '#x_label# <br>#val# #key# ' );
					//------------------------------//
					$g->set_width( '100%' );
					$g->set_height( 300 );
					$g->set_js_path ( REPORTS_PLUGIN_URL . 'report-graphs/open-flash-chart/js/' );
					$g->set_swf_path ( REPORTS_PLUGIN_URL . 'report-graphs/open-flash-chart/' );
					$g->set_output_type('js');
					echo $g->render();
					echo '</div>';
				} else {
					echo '<div class="notice notice-info"><p>' . esc_html__('Keine Daten vorhanden für diesen Zeitraum.', 'postindexer') . '</p></div>';
				}
				//=======================================//
			}
		break;
		//---------------------------------------------------//
	}
}

?>
