<?php

if ( ! class_exists( 'Postindexer_Monitoring_Admin' ) ) {

class Postindexer_Monitoring_Admin {

    private $tools = [];

    public function __construct() {
        // Monitoring-Tools registrieren (weitere können hier ergänzt werden)
        $this->tools = [
            [
                'key' => 'reports',
                'name' => 'Netzwerk Reports',
                'desc' => 'Statistiken und Aktivitäten über das gesamte Netzwerk.',
                'file' => dirname(__DIR__) . '/includes/reports/reports.php',
                'class' => 'Activity_Reports',
                'method' => 'page_output',
            ],
            [
                'key' => 'user_reports',
                'name' => 'User Reports',
                'desc' => 'Berichte und Statistiken zur Nutzeraktivität im Netzwerk.',
                'file' => dirname(__DIR__) . '/includes/user-reports/user-reports.php',
                'class' => 'UserReports',
                'method' => 'user_reports_admin_show_panel',
            ],
            [
                'key' => 'blog_activity',
                'name' => 'Blog Activity',
                'desc' => 'Aktivitätsstatistiken zu Blogs, Beiträgen und Kommentaren im Netzwerk.',
                'file' => dirname(__DIR__) . '/includes/blog-activity/blog-activity.php',
                'class' => 'Blog_Activity',
                'method' => 'page_main_output',
            ],
            [
                'key' => 'content_monitor',
                'name' => 'Content Monitor',
                'desc' => 'Überwacht und meldet neue oder geänderte Inhalte im Netzwerk.',
                'file' => dirname(__DIR__) . '/includes/content-monitor/content-monitor.php',
                'class' => 'Content_Monitor',
                'method' => 'page_main_output',
            ],
            [
                'key' => 'user_activity',
                'name' => 'User Activity',
                'desc' => 'Zeigt Nutzeraktivitäten und Netzwerk-Logins an.',
                'file' => dirname(__DIR__) . '/includes/user-activity/user-activity.php',
                'class' => 'User_Activity',
                'method' => 'page_main_output',
            ],
            // Weitere Tools können hier ergänzt werden
        ];

        // Toggle-Handler früh ausführen, bevor Output startet
        add_action('admin_init', [$this, 'handle_toggle']);

        // AJAX Toggle
        add_action('wp_ajax_ps_monitoring_toggle', [$this, 'ajax_toggle']);
        
        // Styles enqueue
        add_action('admin_head', [$this, 'enqueue_styles']);
    }
    
    public function enqueue_styles() {
        wp_enqueue_style('content-monitor-styles', plugins_url('css/content-monitor.css', dirname(__FILE__)), [], '1.0.0');
    }

    private function get_tool_by_key($key) {
        foreach ($this->tools as $tool) {
            if ($tool['key'] === $key) return $tool;
        }
        return null;
    }

    public function handle_toggle() {
        if (empty($_POST['ps_monitoring_toggle'])) return;
        if (!current_user_can('manage_network_options')) return;
        // Nur auf Monitoring-Seite reagieren
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page !== 'ps-multisite-index-monitoring') return;

        check_admin_referer('ps_monitoring_toggle');
        $toggle_key = isset($_POST['tool_key']) ? sanitize_key($_POST['tool_key']) : '';
        if (isset($_POST['tool_state'])) {
            $toggle_state = $_POST['tool_state'] === 'on';
        } else {
            $toggle_state = isset($_POST['tool_state_checkbox']) && $_POST['tool_state_checkbox'] === 'on';
        }
        if ($toggle_key) {
            $this->set_tool_enabled($toggle_key, $toggle_state);
            // Redirect before any output
            wp_safe_redirect(add_query_arg(['page' => 'ps-multisite-index-monitoring', 'tab' => $toggle_key], network_admin_url('admin.php')));
            exit;
        }
    }

    public function ajax_toggle() {
        if (!current_user_can('manage_network_options')) wp_send_json_error(['message' => 'forbidden'], 403);
        check_ajax_referer('ps_monitoring_toggle', 'nonce');

        $toggle_key = isset($_POST['tool_key']) ? sanitize_key($_POST['tool_key']) : '';
        $toggle_state = isset($_POST['tool_state']) && $_POST['tool_state'] === 'on';
        $tool = $this->get_tool_by_key($toggle_key);
        if (!$tool) {
            wp_send_json_error(['message' => 'tool_not_found'], 404);
        }

        $this->set_tool_enabled($toggle_key, $toggle_state);
        $status = $this->get_tool_status($tool);

        wp_send_json_success([
            'enabled' => $status['enabled'],
            'status_class' => $status['status_class'],
            'badge' => $status['badge'],
            'label' => $status['enabled'] ? __('Aktiv', 'postindexer') : __('Inaktiv', 'postindexer')
        ]);
    }

    public function render_monitoring_page() {
        // Aktiven Tab ermitteln
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'blog_activity';
        
        // Tool-Status sammeln für Toolbar
        $tool_status = [];
        foreach ($this->tools as $tool) {
            $status = $this->get_tool_status($tool);
            $tool_status[$tool['key']] = $status;
        }
        
        echo '<div class="wrap ps-monitoring-wrap">';
        echo '<h1>' . esc_html__( 'Netzwerk Monitoring', 'postindexer' ) . '</h1>';
        
        // Toolbar mit Status-Übersicht
        echo '<div class="ps-monitoring-toolbar">';
        echo '<div class="toolbar-stats">';
        
        $active_tools = count(array_filter($tool_status, function($s) { return $s['active']; }));
        $total_tools = count($this->tools);
        
        echo '<div class="stat-item">';
        echo '<span class="stat-icon">📊</span>';
        echo '<div class="stat-info">';
        echo '<span class="stat-value">' . $active_tools . '/' . $total_tools . '</span>';
        echo '<span class="stat-label">Aktive Tools</span>';
        echo '</div>';
        echo '</div>';
        
        // Weitere Statistiken aus den Tools
        foreach ($this->tools as $tool) {
            if (!empty($tool_status[$tool['key']]['stat'])) {
                $stat = $tool_status[$tool['key']]['stat'];
                echo '<div class="stat-item">';
                echo '<span class="stat-icon">' . $stat['icon'] . '</span>';
                echo '<div class="stat-info">';
                echo '<span class="stat-value">' . esc_html($stat['value']) . '</span>';
                echo '<span class="stat-label">' . esc_html($stat['label']) . '</span>';
                echo '</div>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '</div>';
        
        // Tab Navigation
        echo '<nav class="ps-monitoring-tabs">';
        foreach ($this->tools as $tool) {
            $is_active = ($active_tab === $tool['key']) ? 'active' : '';
            $status_class = $tool_status[$tool['key']]['status_class'];
            $url = add_query_arg(['page' => 'ps-multisite-index-monitoring', 'tab' => $tool['key']], network_admin_url('admin.php'));
            
            echo '<a href="' . esc_url($url) . '" class="tab-link ' . $is_active . ' ' . $status_class . '" data-tool-key="' . esc_attr($tool['key']) . '">';
            echo '<span class="tab-status"></span>';
            echo '<span class="tab-name">' . esc_html($tool['name']) . '</span>';
            if (!empty($tool_status[$tool['key']]['badge'])) {
                echo '<span class="tab-badge">' . esc_html($tool_status[$tool['key']]['badge']) . '</span>';
            }
            echo '</a>';
        }
        echo '</nav>';
        
        // Tab Content
        echo '<div class="ps-monitoring-content">';
        
        foreach ($this->tools as $tool) {
            if ($active_tab !== $tool['key']) continue;
            
            echo '<div class="tab-panel active" id="tab-' . esc_attr($tool['key']) . '">';
            echo '<div class="tab-header">';
            echo '<h2>' . esc_html($tool['name']) . '</h2>';
            echo '<p class="tab-description">' . esc_html($tool['desc']) . '</p>';
            
            // Alerts-Box anzeigen wenn vorhanden
            if (!empty($tool_status[$tool['key']]['alerts'])) {
                echo '<div class="tool-alerts">';
                echo '<h3><span class="dashicons dashicons-yes-alt"></span> ' . sprintf(__('%d verfügbare Reports', 'postindexer'), count($tool_status[$tool['key']]['alerts'])) . '</h3>';
                echo '<ul class="alerts-list">';
                foreach ($tool_status[$tool['key']]['alerts'] as $alert) {
                    echo '<li>';
                    echo '<a href="#" data-psource-modal-open="report-modal" data-report="' . esc_attr($alert['nicename']) . '">';
                    echo '<strong>' . esc_html($alert['title']) . '</strong>';
                    echo '<span class="alert-desc">' . esc_html($alert['desc']) . '</span>';
                    echo '</a>';
                    echo '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            
            echo '<div class="tab-toolbar">';
            echo '<form method="post" class="tab-toggle-form" data-tool="' . esc_attr($tool['key']) . '">';
            wp_nonce_field('ps_monitoring_toggle');
            echo '<input type="hidden" name="ps_monitoring_toggle" value="1" />';
            echo '<input type="hidden" name="tool_key" value="' . esc_attr($tool['key']) . '" />';
            $enabled = $tool_status[$tool['key']]['enabled'];
            echo '<input type="hidden" name="tool_state" value="' . ($enabled ? 'on' : 'off') . '" />';
            echo '<label class="toggle-switch">';
            echo '<input type="checkbox" name="tool_state_checkbox" class="tool-toggle" data-key="' . esc_attr($tool['key']) . '" ' . checked($enabled, true, false) . ' />';
            echo '<span class="slider"></span>';
            echo '</label>';
            $btn_label = $enabled ? __('Deaktivieren', 'postindexer') : __('Aktivieren', 'postindexer');
            echo '<button type="submit" class="screen-reader-text">' . esc_html($btn_label) . '</button>';
            echo '<span class="tool-state-label ' . esc_attr($tool_status[$tool['key']]['status_class']) . '">';
            echo $enabled ? __('Aktiv', 'postindexer') : __('Inaktiv', 'postindexer');
            if (!empty($tool_status[$tool['key']]['badge'])) {
                echo ' · ' . sprintf(__('Meldungen: %s', 'postindexer'), esc_html($tool_status[$tool['key']]['badge']));
            }
            echo '</span>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="tab-body">';
            $this->render_tool_content($tool);
            echo '</div>';
            
            echo '</div>';
        }
        
        echo '</div>'; // .ps-monitoring-content
        
        // Styles
        $this->render_styles();
        
        // Modal für Reports
        $this->render_modal();

        // Interaktion
        $this->render_scripts();
        
        echo '</div>'; // .wrap
    }
    
    private function get_tool_status($tool) {
        $enabled = $this->is_tool_enabled($tool['key']);
        $status = [
            'active' => false,
            'enabled' => $enabled,
            'badge' => '',
            'stat' => null,
            'status_class' => 'status-off',
            'alerts' => []
        ];
        
        // Prüfe ob Tool verfügbar ist
        if ($enabled && file_exists($tool['file'])) {
            $status['active'] = true;
        }
        
        // Tool-spezifische Statistiken & Badges
        switch ($tool['key']) {
            case 'blog_activity':
                $status['stat'] = [
                    'icon' => '🌐',
                    'value' => get_blog_count(),
                    'label' => 'Netzwerk-Seiten'
                ];
                break;
            case 'user_activity':
                $count_users = count_users();
                $status['stat'] = [
                    'icon' => '👥',
                    'value' => $count_users['total_users'],
                    'label' => 'Registrierte Nutzer'
                ];
                break;
            case 'reports':
                if (class_exists('Activity_Reports')) {
                    $reports_instance = Activity_Reports::instance();
                    if (!empty($reports_instance->available_reports)) {
                        $count = count($reports_instance->available_reports);
                        $status['badge'] = (string) $count;
                        $status['alerts'] = [];
                        foreach ($reports_instance->available_reports as $report) {
                            $status['alerts'][] = [
                                'title' => $report[0],
                                'nicename' => $report[1],
                                'desc' => $report[2]
                            ];
                        }
                        // Reports verfügbar = grün statt gelb
                        $status['status_class'] = 'status-active';
                    }
                }
                break;
            case 'content_monitor':
                $log = get_site_option( 'content_monitor_log', array() );
                if ( is_array( $log ) && !empty( $log ) ) {
                    $status['badge'] = (string) count( $log );
                }
                break;
            case 'user_reports':
                // keine spezielle Statistik, nur Status
                break;
        }
        
        // Statusfarbe bestimmen (aber nur wenn nicht bereits gesetzt)
        if (empty($status['status_class']) || $status['status_class'] === 'status-off') {
            if (!$enabled) {
                $status['status_class'] = 'status-off';
            } elseif (!empty($status['badge'])) {
                $status['status_class'] = 'status-alert';
            } elseif ($status['active']) {
                $status['status_class'] = 'status-active';
            } else {
                $status['status_class'] = 'status-off';
            }
        }
        
        return $status;
    }

    private function get_enabled_tools() {
        $option = get_site_option('ps_monitoring_enabled_tools', null);
        if ($option === null) {
            // Default: alles aktiv außer User Reports (bisher als inaktiv wahrgenommen)
            $defaults = array_map(function($t){ return $t['key']; }, $this->tools);
            $defaults = array_filter($defaults, function($k){ return $k !== 'user_reports'; });
            update_site_option('ps_monitoring_enabled_tools', $defaults);
            return $defaults;
        }
        return is_array($option) ? $option : [];
    }

    private function is_tool_enabled($key) {
        $enabled = $this->get_enabled_tools();
        return in_array($key, $enabled, true);
    }

    private function set_tool_enabled($key, $state) {
        $enabled = $this->get_enabled_tools();
        if ($state) {
            if (!in_array($key, $enabled, true)) {
                $enabled[] = $key;
            }
        } else {
            $enabled = array_values(array_diff($enabled, [$key]));
        }
        update_site_option('ps_monitoring_enabled_tools', $enabled);
    }
    
    private function render_tool_content($tool) {
        if (!file_exists($tool['file'])) {
            echo '<div class="notice notice-error"><p>Tool-Datei nicht gefunden.</p></div>';
            return;
        }
        
        require_once $tool['file'];
        
        if (!class_exists($tool['class'])) {
            echo '<div class="notice notice-error"><p>Tool-Klasse nicht gefunden.</p></div>';
            return;
        }
        
        // Instanz erstellen
        if ($tool['class'] === 'Activity_Reports') {
            $instance = Activity_Reports::instance();
        } else {
            $instance = new $tool['class']();
        }
        
        // Für UserReports globale Variable setzen
        if ($tool['class'] === 'UserReports') {
            global $user_reports;
            $user_reports = $instance;
        }
        
        // Content rendern
        if (method_exists($instance, $tool['method'])) {
            ob_start();
            $instance->{$tool['method']}();
            $content = ob_get_clean();
            
            // Reports-Links in Modal-Trigger umwandeln
            if ($tool['key'] === 'reports' && !empty($content)) {
                $content = preg_replace_callback(
                    '/<a href=\'([^\']+)\' rel=\'permalink\' class=\'edit\'>([^<]+)<\/a>/',
                    function($matches) {
                        if (preg_match('/report=([a-zA-Z0-9\-_]+)/', $matches[1], $rm)) {
                            $report = esc_attr($rm[1]);
                            return '<a href="#" data-psource-modal-open="report-modal" data-report="' . $report . '">' . esc_html($matches[2]) . '</a>';
                        }
                        return $matches[0];
                    },
                    $content
                );
            }
            
            echo $content;
        } else {
            echo '<div class="notice notice-warning"><p>Ausgabemethode nicht gefunden.</p></div>';
        }
    }

    private function render_scripts() {
        $badge_label = __('Meldungen: %s', 'postindexer');
        $error_label = __('Konnte den Status nicht speichern.', 'postindexer');
        $nonce = wp_create_nonce('ps_monitoring_toggle');
        echo '<script>
        (function($){
            var config = {
                nonce: ' . json_encode($nonce) . ',
                badgeLabel: ' . json_encode($badge_label) . ',
                errorText: ' . json_encode($error_label) . '
            };

            $(document).on("change", ".tab-toggle-form .tool-toggle", function(){
                var $checkbox = $(this);
                var key = $checkbox.data("key");
                var on = $checkbox.is(":checked");
                var prev = !on;
                var $form = $checkbox.closest("form");
                $checkbox.prop("disabled", true);
                $form.find("input[name=tool_state]").val(on ? "on" : "off");

                $.post(ajaxurl, {
                    action: "ps_monitoring_toggle",
                    nonce: config.nonce,
                    tool_key: key,
                    tool_state: on ? "on" : "off"
                }).done(function(resp){
                    if (!resp || !resp.success) {
                        throw new Error("request failed");
                    }
                    var data = resp.data || {};
                    var $label = $form.find(".tool-state-label");
                    $label.removeClass("status-active status-off status-alert").addClass(data.status_class || "");
                    var labelText = data.label || (on ? "Aktiv" : "Inaktiv");
                    if (data.badge) {
                        labelText += " · " + config.badgeLabel.replace("%s", data.badge);
                    }
                    $label.text(labelText);
                    $form.find("input[name=tool_state]").val(data.enabled ? "on" : "off");
                    $checkbox.prop("checked", !!data.enabled);

                    var $tabLink = $(".ps-monitoring-tabs .tab-link[data-tool-key=\"" + key + "\"]");
                    $tabLink.removeClass("status-active status-off status-alert").addClass(data.status_class || "");
                    var $badge = $tabLink.find(".tab-badge");
                    if (data.badge) {
                        if (!$badge.length) {
                            $badge = $(\'<span class="tab-badge" />\').appendTo($tabLink);
                        }
                        $badge.text(data.badge);
                    } else {
                        $badge.remove();
                    }
                }).fail(function(){
                    $checkbox.prop("checked", prev);
                    $form.find("input[name=tool_state]").val(prev ? "on" : "off");
                    window.alert(config.errorText);
                }).always(function(){
                    $checkbox.prop("disabled", false);
                });
            });
        })(jQuery);
        </script>';
    }
    
    private function render_styles() {
        echo '<style>
        .ps-monitoring-wrap {
            background: #f5f5f5;
            margin: -20px -20px 0 -22px;
            padding: 20px 20px 0 20px;
        }
        .ps-monitoring-wrap > h1 {
            background: #fff;
            padding: 1.5em 2em;
            margin: 0 0 2em 0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .ps-monitoring-toolbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 1.5em 2em;
            border-radius: 10px;
            margin-bottom: 2em;
            box-shadow: 0 4px 16px rgba(102,126,234,0.2);
        }
        .toolbar-stats {
            display: flex;
            gap: 3em;
            flex-wrap: wrap;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 1em;
        }
        .stat-icon {
            font-size: 2.5em;
            line-height: 1;
        }
        .stat-info {
            display: flex;
            flex-direction: column;
        }
        .stat-value {
            font-size: 1.8em;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .ps-monitoring-tabs {
            display: flex;
            gap: 0.5em;
            margin-bottom: 0;
            background: #fff;
            padding: 0.5em 0.5em 0 0.5em;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .tab-link {
            display: flex;
            align-items: center;
            gap: 0.6em;
            padding: 0.9em 1.5em;
            background: #f5f5f5;
            border: none;
            border-radius: 8px 8px 0 0;
            color: #555;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            position: relative;
        }
        .tab-link:hover {
            background: #e8e8e8;
            color: #333;
        }
        .tab-link.active {
            background: linear-gradient(135deg, #0073aa 0%, #005177 100%);
            color: #fff;
            box-shadow: 0 -2px 8px rgba(0,115,170,0.2);
        }
        .tab-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ccc;
        }
        .tab-link.status-active .tab-status {
            background: #2ecc40;
            box-shadow: 0 0 8px rgba(46,204,64,0.6);
        }
        .tab-link.status-off .tab-status {
            background: #ff4d4f;
            box-shadow: 0 0 8px rgba(255,77,79,0.4);
        }
        .tab-link.status-alert .tab-status {
            background: #f1c40f;
            box-shadow: 0 0 10px rgba(241,196,15,0.6);
        }
        .tab-badge {
            background: rgba(255,255,255,0.3);
            padding: 0.2em 0.6em;
            border-radius: 10px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .tab-link.active .tab-badge {
            background: rgba(255,255,255,0.25);
        }
        .ps-monitoring-content {
            background: #fff;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            min-height: 400px;
        }
        .tab-panel {
            display: none;
            padding: 2em;
        }
        .tab-panel.active {
            display: block;
        }
        .tab-header {
            margin-bottom: 2em;
            padding-bottom: 1em;
            border-bottom: 2px solid #f0f0f0;
        }
        .tab-header h2 {
            margin: 0 0 0.5em 0;
            color: #0073aa;
            font-size: 1.8em;
        }
        .tab-description {
            margin: 0;
            color: #666;
            font-size: 1.05em;
        }
        .tool-alerts {
            background: linear-gradient(135deg, #d4edda 0%, #e8f5e9 100%);
            border-left: 4px solid #28a745;
            padding: 1.5em;
            margin: 1em 0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(40,167,69,0.15);
        }
        .tool-alerts h3 {
            margin: 0 0 1em 0;
            color: #155724;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 0.5em;
        }
        .tool-alerts h3 .dashicons {
            color: #28a745;
        }
        .alerts-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 0.75em;
        }
        .alerts-list li {
            background: #fff;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .alerts-list li:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .alerts-list a {
            display: block;
            padding: 1em;
            text-decoration: none;
            color: inherit;
        }
        .alerts-list strong {
            display: block;
            color: #0073aa;
            font-size: 1.05em;
            margin-bottom: 0.3em;
        }
        .alerts-list .alert-desc {
            display: block;
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
        }
        .tab-body {
            /* Bestehende Tool-Styles bleiben erhalten */
        }
        .tab-toolbar {
            display: flex;
            align-items: center;
            gap: 1em;
            margin-top: 0.5em;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-switch .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #d9d9d9;
            transition: .3s;
            border-radius: 28px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle-switch .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .toggle-switch input:checked + .slider {
            background: linear-gradient(135deg, #4caf50 0%, #2e8b57 100%);
        }
        .toggle-switch input:checked + .slider:before {
            transform: translateX(26px);
        }
        .toggle-switch input:disabled + .slider {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .tab-toolbar .tool-state-label {
            font-weight: 600;
            padding: 0.2em 0.6em;
            border-radius: 8px;
            background: #f5f5f5;
        }
        .tab-toolbar .tool-state-label.status-active {
            background: #e6f7e6;
            color: #237804;
        }
        .tab-toolbar .tool-state-label.status-off {
            background: #fff1f0;
            color: #a8071a;
        }
        .tab-toolbar .tool-state-label.status-alert {
            background: #fffbe6;
            color: #ad6800;
        }
        /* Responsive */
        @media (max-width: 900px) {
            .ps-monitoring-tabs {
                flex-wrap: wrap;
            }
            .toolbar-stats {
                gap: 1.5em;
            }
            .stat-item {
                flex: 1 1 200px;
            }
        }
        </style>';
    }
    
    private function render_modal() {
        if (!defined('PSOURCE_REPORT_MODAL')) {
            define('PSOURCE_REPORT_MODAL', true);
            $plugin_url = plugins_url('assets/psource-ui/modal/', WP_PLUGIN_DIR . '/ps-postindexer/ps-postindexer.php');
            echo '<link rel="stylesheet" href="' . $plugin_url . 'psource-modal.css?ver=1.0.0" />';
            echo '<dialog id="report-modal" class="psource-modal">';
            echo '<div class="psource-modal-header">';
            echo '<span id="psource-modal-title"></span>';
            echo '<button class="psource-modal-close" aria-label="Schließen">&times;</button>';
            echo '</div>';
            echo '<div class="psource-modal-content" id="psource-modal-content"></div>';
            echo '</dialog>';
            echo '<script src="' . $plugin_url . 'psource-modal.js?ver=1.0.0"></script>';
            
            // AJAX-Loader für Reports
            echo '<script>
            jQuery(document).on("click", "[data-psource-modal-open][data-report]", function(e) {
                e.preventDefault();
                var report = jQuery(this).data("report");
                var title = jQuery(this).text();
                var modal = document.getElementById("report-modal");
                jQuery("#psource-modal-title").text(title);
                jQuery("#psource-modal-content").html("<div>Lade Report...</div>");
                try { modal.showModal(); } catch(err) {}
                jQuery.ajax({
                    url: ajaxurl,
                    method: "POST",
                    data: {
                        action: "psource_load_report",
                        report: report
                    },
                    success: function(html) {
                        jQuery("#psource-modal-content").html(html);
                        // Formular im Modal abfangen
                        jQuery("#psource-modal-content form[name=report]").on("submit", function(ev) {
                            ev.preventDefault();
                            var formData = jQuery(this).serializeArray();
                            formData.push({name: "action", value: "psource_load_report"});
                            formData.push({name: "report", value: report});
                            var submitBtn = jQuery(this).find("input[type=\'submit\'][name=\'Submit\']");
                            if(submitBtn.length) {
                                formData.push({name: "Submit", value: submitBtn.val()});
                            }
                            jQuery("#psource-modal-content").html("<div>Lade Report...</div>");
                            jQuery.ajax({
                                url: ajaxurl,
                                method: "POST",
                                data: formData,
                                success: function(html2) {
                                    jQuery("#psource-modal-content").html(html2);
                                },
                                error: function() {
                                    jQuery("#psource-modal-content").html("<div>Fehler beim Laden des Reports.</div>");
                                }
                            });
                        });
                    },
                    error: function() {
                        jQuery("#psource-modal-content").html("<div>Fehler beim Laden des Reports.</div>");
                    }
                });
            });
            </script>';
            
            // Polyfill für <dialog>
            echo '<script>
            (function(){
                var modal = document.getElementById("report-modal");
                if (modal && !modal.showModal) {
                    modal.showModal = function() {
                        this.setAttribute("open", "open");
                        this.style.display = "block";
                        this.style.position = "fixed";
                        this.style.zIndex = 99999;
                        this.style.left = "50%";
                        this.style.top = "50%";
                        this.style.transform = "translate(-50%, -50%)";
                        document.body.style.overflow = "hidden";
                    };
                    modal.close = function() {
                        this.removeAttribute("open");
                        this.style.display = "none";
                        document.body.style.overflow = "";
                    };
                }
                var closeBtn = modal ? modal.querySelector(".psource-modal-close") : null;
                if (closeBtn) {
                    closeBtn.addEventListener("click", function(e) {
                        e.preventDefault();
                        modal.close();
                    });
                }
            })();
            </script>';
        }
    }
}

}
