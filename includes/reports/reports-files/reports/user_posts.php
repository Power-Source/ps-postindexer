<?php
require_once dirname( __DIR__ ) . '/report-graphs/open-flash-chart/open-flash-chart.php';

// Stelle sicher, dass Konstanten definiert sind
if( ! defined( 'REPORTS_PLUGIN_DIR' ) )
	define( 'REPORTS_PLUGIN_DIR', plugin_dir_path( dirname(__DIR__) ) . 'reports-files/' );

if( ! defined( 'REPORTS_PLUGIN_URL' ) )
	define( 'REPORTS_PLUGIN_URL', plugin_dir_url( dirname(__DIR__) ) . 'reports-files/' );

global $activity_reports;
if (!isset($activity_reports) || !is_object($activity_reports)) return;

$activity_reports->add_report( __( 'User Posts', 'reports' ), 'user-posts', __( 'Displays post activity for a user', 'reports' ) );

if (
    (isset($_GET['report']) && $_GET['report'] === 'user-posts') ||
    (isset($_POST['report']) && $_POST['report'] === 'user-posts')
) {
    add_action('view_report', 'report_user_posts_ouput' );
}

function report_user_posts_ouput(){
	global $wpdb, $current_site;

	$action = isset( $_GET[ 'report-action' ] ) ? $_GET[ 'report-action' ] : '';
	switch( $action ) {
		//---------------------------------------------------//
		default:
			?>
			<form name="report" method="POST" action="?page=reports&action=view-report&report=user-posts&report-action=view">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e( 'Username', 'reports' ) ?></th>
						<td>
							<input type="text" name="user_login" id="user_login" list="user_login_list" style="width: 95%" tabindex='1' maxlength="200" value="" />
							<datalist id="user_login_list"></datalist>
							<br />
							<?php _e( 'Case Sensitive', 'reports' ) ?>
						</td>
					</tr>
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
			<script>
			(function() {
				const userInput = document.getElementById('user_login');
				const userList = document.getElementById('user_login_list');
				let debounceTimeout;

				userInput.addEventListener('input', function() {
					clearTimeout(debounceTimeout);
					const term = this.value.trim();
					
					if (term.length < 2) {
						userList.innerHTML = '';
						return;
					}

					debounceTimeout = setTimeout(function() {
						fetch(ajaxurl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: new URLSearchParams({
								action: 'reports_search_users',
								term: term
							})
						})
						.then(response => response.json())
						.then(data => {
							userList.innerHTML = '';
							data.forEach(user => {
								const option = document.createElement('option');
								option.value = user;
								userList.appendChild(option);
							});
						})
						.catch(error => console.error('Error fetching users:', error));
					}, 300);
				});
			})();
			</script>
			<?php
		break;
		//---------------------------------------------------//
		case 'view':
			$user_login = sanitize_user( $_POST['user_login'] );
			$period = absint( $_POST['period'] );
			$user = get_user_by( 'login', $user_login );
			if ( ! $user ) {
				?>
                <p><?php _e( 'User not found.', 'reports' ); ?></p>
                <?php
			} else {
				$user_ID = $user->ID;
				?>
                <p>
                    <ul>
                        <li><strong><?php _e( 'Username', 'reports' ); ?></strong>: <?php echo $user_login; ?></li>
                        <li><strong><?php _e( 'Period', 'reports' ); ?></strong>: <?php printf( __( '%d Days', 'reports' ), $period ); ?></li>
                    </ul>
                </p>
                <?php
				//=======================================//
				// Nutze die neue Data Source aus dem Plugin-Core
				$data_source = $activity_reports->get_data_source();
				$total_days = $period;
				$date_format = get_option('date_format');

				// Hole Daten direkt aus dem indexierten Post Index
				$counts_by_date = $data_source->get_user_posts_by_date( $user_ID, $period, 'post' );

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
				//=======================================//
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
				$g->area_hollow( 1, 3, 4, '#3357A0', __( 'post(s)', 'reports' ), 10 );

				$g->set_tool_tip( '#x_label# <br>#val# #key# ' );
				//------------------------------//
				$g->set_width( '100%' );
				$g->set_height( 250 );
				$g->set_js_path ( REPORTS_PLUGIN_URL . 'report-graphs/open-flash-chart/js/' );
				$g->set_swf_path ( REPORTS_PLUGIN_URL . 'report-graphs/open-flash-chart/' );
				$g->set_output_type('js');
				echo $g->render();
				//=======================================//
			}
		break;
		//---------------------------------------------------//
	}
}
