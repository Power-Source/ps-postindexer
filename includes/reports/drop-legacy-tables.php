<?php
/**
 * Migration Script: Drop Legacy Activity Tables
 * 
 * Entfernt die veralteten Activity-Tabellen, die nicht mehr benötigt werden.
 * Reports nutzen jetzt direkt den Post Index (network_posts) über Reports_Data_Source.
 * 
 * WICHTIG: Dieses Skript sollte nur einmal ausgeführt werden!
 * 
 * Um die Tabellen zu löschen, rufe diese Datei über WP-CLI oder direkt auf:
 * wp eval-file /path/to/drop-legacy-tables.php
 * 
 * Oder füge diese Zeile temporär in die Hauptdatei ein:
 * require_once( __DIR__ . '/includes/reports/drop-legacy-tables.php' );
 * postindexer_drop_legacy_activity_tables();
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Entfernt die veralteten Activity-Tabellen
 * 
 * @return array Status-Report mit gelöschten Tabellen
 */
function postindexer_drop_legacy_activity_tables() {
	global $wpdb;

	// Liste der zu entfernenden Tabellen
	$tables_to_drop = array(
		'reports_user_activity',
		'reports_post_activity',
		'reports_comment_activity',
		'reports_page_activity',
	);

	$results = array();

	foreach ( $tables_to_drop as $table_name ) {
		$full_table_name = $wpdb->base_prefix . $table_name;
		
		// Prüfe ob Tabelle existiert
		$table_exists = $wpdb->get_var( 
			$wpdb->prepare( 
				"SHOW TABLES LIKE %s", 
				$full_table_name 
			) 
		);

		if ( $table_exists ) {
			// Zeige Anzahl der Einträge vor dem Löschen
			$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$full_table_name}`" );
			
			// Lösche die Tabelle
			$dropped = $wpdb->query( "DROP TABLE IF EXISTS `{$full_table_name}`" );
			
			if ( $dropped !== false ) {
				$results[ $table_name ] = array(
					'status' => 'dropped',
					'rows' => $row_count,
					'message' => sprintf( 
						__( 'Tabelle %s wurde gelöscht (%d Einträge)', 'postindexer' ), 
						$full_table_name, 
						$row_count 
					)
				);
			} else {
				$results[ $table_name ] = array(
					'status' => 'error',
					'message' => sprintf( 
						__( 'Fehler beim Löschen von %s', 'postindexer' ), 
						$full_table_name 
					)
				);
			}
		} else {
			$results[ $table_name ] = array(
				'status' => 'not_found',
				'message' => sprintf( 
					__( 'Tabelle %s existiert nicht', 'postindexer' ), 
					$full_table_name 
				)
			);
		}
	}

	// Setze Migrations-Flag
	update_site_option( 'postindexer_legacy_tables_dropped', 'yes' );
	update_site_option( 'postindexer_legacy_tables_dropped_date', current_time( 'mysql' ) );

	return $results;
}

/**
 * Admin-Notice nach erfolgreicher Migration
 */
function postindexer_legacy_tables_dropped_notice() {
	if ( get_site_option( 'postindexer_legacy_tables_dropped' ) === 'yes' ) {
		$date = get_site_option( 'postindexer_legacy_tables_dropped_date' );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php _e( 'Post Indexer - Legacy Activity Tables', 'postindexer' ); ?></strong><br>
				<?php printf( 
					__( 'Die veralteten Activity-Tabellen wurden am %s erfolgreich entfernt. Reports nutzen jetzt direkt den Post Index.', 'postindexer' ),
					$date
				); ?>
			</p>
		</div>
		<?php
	}
}

// Nur ausführen wenn direkt aufgerufen oder via WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$results = postindexer_drop_legacy_activity_tables();
	WP_CLI::success( 'Legacy Activity Tables wurden entfernt:' );
	print_r( $results );
}

// Optional: Automatische Ausführung beim Plugin-Upgrade
// Uncomment diese Zeilen, um die Migration automatisch durchzuführen
/*
if ( get_site_option( 'postindexer_legacy_tables_dropped' ) !== 'yes' ) {
	add_action( 'admin_init', 'postindexer_drop_legacy_activity_tables' );
	add_action( 'network_admin_notices', 'postindexer_legacy_tables_dropped_notice' );
}
*/
