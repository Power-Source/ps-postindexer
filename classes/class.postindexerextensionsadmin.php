<?php

if ( ! class_exists( 'Postindexer_Extensions_Admin' ) ) {

class Postindexer_Extensions_Admin {

    private $extensions = [
        'recent_network_posts' => [
            'name' => 'Aktuelle Netzwerkbeiträge',
            'desc' => 'Zeigt eine anpassbare Liste der letzten Beiträge aus dem gesamten Multisite-Netzwerk an. Die Ausgabe erfolgt per Shortcode: [recent_network_posts] – einfach auf einer beliebigen Seite oder im Block-Editor einfügen.',
            'settings_page' => 'network-posts-settings',
        ],
        'global_site_search' => [
            'name' => 'Globale Netzwerksuche',
            'desc' => 'Ermöglicht eine zentrale Suche über alle Seiten und Beiträge im gesamten Multisite-Netzwerk. Erstellt bei Aktivierung eine Suchseite (/site-search) und stellt Netzwerkweite Such Widgets bereit',
            'settings_page' => '', // ggf. später ergänzen
        ],
        'recent_global_posts_widget' => [
            'name' => 'Neueste Netzwerk Beiträge',
            'desc' => 'Stellt ein Widget bereit, das die neuesten Beiträge aus dem gesamten Netzwerk anzeigt. Alle Einstellungen legst du für jedes Widget separat an, wähle hier aus wo du das Widget erlauben willst',
            'settings_page' => '',
        ],
        'global_site_tags' => [
            'name' => 'Netzwerk Seiten-Tags',
            'desc' => 'Ermöglicht die Anzeige und Verwaltung globaler Schlagwörter (Tags) im gesamten Netzwerk. Es wird automatisch eine Seite mit dem Namen „Tags“ auf deinem Hauptblog erstellt, auf der jeder alle Blogs durchsuchen kann.',
            'settings_page' => '',
        ],
        'live_stream_widget' => [
            'name' => 'Live Stream Widget',
            'desc' => 'Zeigt die neuesten Beiträge und Kommentare in einem Live-Stream-Widget an.',
            'settings_page' => '',
        ],
        'recent_global_comments_widget' => [
            'name' => 'Global Comments Widget',
            'desc' => 'Stellt ein Widget bereit, das die neuesten Kommentare aus dem gesamten Netzwerk anzeigt.',
            'settings_page' => '',
            'requires_comment_indexer' => true
        ],
        'recent_comments' => [
            'name' => 'Netzwerk Kommentare',
            'desc' => 'Zeigt die letzten Netzwerk-Kommentare per Shortcode [network_comments] an. Die zentrale Konfiguration erfolgt im Netzwerk-Admin unter den Erweiterungs-Einstellungen. (Comment Indexer erforderlich)',
            'settings_page' => '',
            'requires_comment_indexer' => true
        ],
        'recent_global_author_comments_feed' => [
            'name' => 'Global Author Comments Feed',
            'desc' => 'Stellt einen globalen Feed aller Kommentare eines Autors im Netzwerk bereit.',
            'settings_page' => '',
            'requires_comment_indexer' => true
        ],
        'comment_form_text' => [
            'name' => 'Comment Form Text',
            'desc' => 'Ermöglicht die Anpassung des Kommentarformular-Textes im gesamten Netzwerk.',
            'settings_page' => ''
        ],
        'comments_control' => [
            'name' => 'Comments Control',
            'desc' => 'Feinjustierung der Kommentar-Drosselung und IP-Whitelist/Blacklist für Kommentare im Netzwerk.',
            'settings_page' => ''
        ],
        'recent_global_author_posts_feed' => [
            'name' => 'Global Author Posts Feed',
            'desc' => 'Stellt einen globalen Feed aller Beiträge eines Autors im Netzwerk bereit.',
            'settings_page' => ''
        ],
        'recent_global_comments_feed' => [
            'name' => 'Recent Global Comments Feed',
            'desc' => 'Stellt einen globalen Feed der neuesten Kommentare im Netzwerk bereit.',
            'settings_page' => '',
            'requires_comment_indexer' => true
        ],
        'recent_global_posts_feed' => [
            'name' => 'Recent Global Posts Feed',
            'desc' => 'Stellt einen globalen Feed der neuesten Beiträge im Netzwerk bereit.',
            'settings_page' => ''
        ],
        // Weitere Erweiterungen können hier ergänzt werden
    ];

    private $option_name = 'postindexer_extensions_settings';

    public function __construct() {}

    public function register_menu( $main_slug, $cap ) {
        add_submenu_page(
            $main_slug,
            __( 'Erweiterungen', 'postindexer' ),
            __( 'Erweiterungen', 'postindexer' ),
            $cap,
            $main_slug . '-extensions',
            array( $this, 'render_extensions_page' )
        );
    }

    public function render_extensions_page() {
        // Prüfen, ob Comment Indexer aktiv ist
        $comment_indexer_active = function_exists('get_site_option') && get_site_option('comment_indexer_active', 0);
        // Speicherlogik für global-site-search
        if (isset($_POST['ps_gss_settings_nonce']) && check_admin_referer('ps_gss_settings_save','ps_gss_settings_nonce')) {
            if (function_exists('global_site_search_site_admin_options_process')) {
                global_site_search_site_admin_options_process();
                echo '<div class="updated notice is-dismissible"><p>Globale Netzwerksuche: Einstellungen gespeichert.</p></div>';
            }
        }
        // Speicherlogik für global-site-tags
        if (isset($_POST['ps_gst_settings_nonce']) && check_admin_referer('ps_gst_settings_save','ps_gst_settings_nonce')) {
            if (function_exists('global_site_tags_site_admin_options_process')) {
                global_site_tags_site_admin_options_process();
                echo '<div class="updated notice is-dismissible"><p>Netzwerk Seiten-Tags: Einstellungen gespeichert.</p></div>';
            }
        }
        // Speicherlogik für Comments Control
        if (isset($_POST['comments_control_settings_nonce']) && check_admin_referer('comments_control_settings_save','comments_control_settings_nonce')) {
            if (isset($_POST['limit_comments_allowed_ips'])) {
                update_site_option('limit_comments_allowed_ips', $_POST['limit_comments_allowed_ips']);
            }
            if (isset($_POST['limit_comments_denied_ips'])) {
                update_site_option('limit_comments_denied_ips', $_POST['limit_comments_denied_ips']);
            }
            echo '<div class="updated notice is-dismissible"><p>Comments Control: Einstellungen gespeichert.</p></div>';
        }
        // Neue Speicherlogik: pro Erweiterung/Card
        foreach ($this->extensions as $key => $ext) {
            if (
                isset($_POST['ps_extension_settings_nonce_' . $key]) &&
                check_admin_referer('ps_extension_settings_save_' . $key, 'ps_extension_settings_nonce_' . $key)
            ) {
                $settings = $this->get_settings();
                // Wenn Erweiterung Comment Indexer benötigt und dieser deaktiviert ist: Status merken, aber nicht aktivieren
                if (!empty($ext['requires_comment_indexer']) && !$comment_indexer_active) {
                    $settings[$key]['active_backup'] = isset($settings[$key]['active']) ? $settings[$key]['active'] : 0;
                    $settings[$key]['active'] = 0;
                } else {
                    $settings[$key]['scope'] = sanitize_text_field($_POST['ps_extensions_scope'][$key] ?? 'main');
                    if ($settings[$key]['scope'] === 'sites') {
                        $settings[$key]['sites'] = array_map('intval', $_POST['ps_extensions_sites'][$key] ?? []);
                    } else {
                        $settings[$key]['sites'] = [];
                    }
                    $settings[$key]['active'] = isset($_POST['ps_extensions_active'][$key]) && $_POST['ps_extensions_active'][$key] === '1' ? 1 : 0;
                    if (!empty($settings[$key]['active_backup']) && $settings[$key]['active']) {
                        unset($settings[$key]['active_backup']);
                    }
                }
                update_site_option($this->option_name, $settings);
                // Erweiterungs-spezifische Settings speichern
                if ($key === 'recent_network_posts' && class_exists('Recent_Network_Posts')) {
                    $recent = new \Recent_Network_Posts();
                    if (method_exists($recent, 'process_settings_form')) {
                        $recent->process_settings_form();
                    }
                } elseif ($key === 'global_site_search' && class_exists('Global_Site_Search_Settings_Renderer')) {
                    $gss = new \Global_Site_Search_Settings_Renderer();
                    if (method_exists($gss, 'process_settings_form')) {
                        $gss->process_settings_form();
                    }
                } elseif ($key === 'global_site_tags') {
                    require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
                    if (class_exists('Global_Site_Tags_Settings_Renderer')) {
                        $gst = new \Global_Site_Tags_Settings_Renderer();
                        if (method_exists($gst, 'process_settings_form')) {
                            $gst->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_posts_widget') {
                    require_once dirname(__DIR__) . '/includes/recent-global-posts-widget/settings.php';
                    if (class_exists('Recent_Global_Posts_Widget_Settings_Renderer')) {
                        $rgpw = new \Recent_Global_Posts_Widget_Settings_Renderer();
                        if (method_exists($rgpw, 'process_settings_form')) {
                            $rgpw->process_settings_form();
                        }
                    }
                } elseif ($key === 'live_stream_widget') {
                    require_once dirname(__DIR__) . '/includes/live-stream-widget/settings.php';
                    if (class_exists('Live_Stream_Widget_Settings_Renderer')) {
                        $lsw = new \Live_Stream_Widget_Settings_Renderer();
                        if (method_exists($lsw, 'process_settings_form')) {
                            $lsw->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_comments_widget') {
                    require_once dirname(__DIR__) . '/includes/recent-global-comments-widget/settings.php';
                    if (class_exists('Recent_Global_Comments_Widget_Settings_Renderer')) {
                        $rgcw = new \Recent_Global_Comments_Widget_Settings_Renderer();
                        if (method_exists($rgcw, 'process_settings_form')) {
                            $rgcw->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_comments') {
                    require_once dirname(__DIR__) . '/includes/recent-comments/settings.php';
                    if (class_exists('Recent_Comments_Settings_Renderer')) {
                        $rcw = new \Recent_Comments_Settings_Renderer();
                        if (method_exists($rcw, 'process_settings_form')) {
                            $rcw->process_settings_form($comment_indexer_active);
                        }
                    }
                } elseif ($key === 'recent_global_author_comments_feed') {
                    require_once dirname(__DIR__) . '/includes/recent-global-author-comments-feed/settings.php';
                    if (class_exists('Recent_Global_Author_Comments_Feed_Settings_Renderer')) {
                        $gacf = new \Recent_Global_Author_Comments_Feed_Settings_Renderer();
                        if (method_exists($gacf, 'process_settings_form')) {
                            $gacf->process_settings_form();
                        }
                    }
                } elseif ($key === 'comment_form_text') {
                    require_once dirname(__DIR__) . '/includes/comment-form-text/comment-form-text.php';
                    if (class_exists('Comment_Form_Text_Settings_Renderer')) {
                        $cft = new \Comment_Form_Text_Settings_Renderer();
                        if (method_exists($cft, 'process_settings_form')) {
                            $cft->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_author_posts_feed') {
                    require_once dirname(__DIR__) . '/includes/recent-global-author-posts-feed/settings.php';
                    if (class_exists('Recent_Global_Author_Posts_Feed_Settings_Renderer')) {
                        $rgapf = new \Recent_Global_Author_Posts_Feed_Settings_Renderer();
                        if (method_exists($rgapf, 'process_settings_form')) {
                            $rgapf->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_comments_feed') {
                    require_once dirname(__DIR__) . '/includes/recent-global-comments-feed/settings.php';
                    if (class_exists('Recent_Global_Comments_Feed_Settings_Renderer')) {
                        $rgcf = new \Recent_Global_Comments_Feed_Settings_Renderer();
                        if (method_exists($rgcf, 'process_settings_form')) {
                            $rgcf->process_settings_form();
                        }
                    }
                } elseif ($key === 'recent_global_posts_feed') {
                    require_once dirname(__DIR__) . '/includes/recent-global-posts-feed/settings.php';
                    if (class_exists('Recent_Global_Posts_Feed_Settings_Renderer')) {
                        $rgpf = new \Recent_Global_Posts_Feed_Settings_Renderer();
                        if (method_exists($rgpf, 'process_settings_form')) {
                            $rgpf->process_settings_form();
                        }
                    }
                }
                echo '<div class="updated notice is-dismissible"><p>Einstellungen für <b>' . esc_html($ext['name']) . '</b> gespeichert.</p></div>';
            }
        }
        $settings = $this->get_settings();
        $sites = get_sites(['fields'=>'ids','number'=>0]);
        $main_site = function_exists('get_main_site_id') ? get_main_site_id() : 1;
        
        // Zähle aktive Erweiterungen
        $active_count = 0;
        $active_extensions = [];
        foreach ($this->extensions as $key => $ext) {
            $active = isset($settings[$key]['active']) ? (int)$settings[$key]['active'] : 0;
            if ($active) {
                $active_count++;
                $scope = $settings[$key]['scope'] ?? 'main';
                $scope_label = $scope === 'network' ? 'Netzwerkweit' : ($scope === 'main' ? 'Hauptseite' : 'Bestimmte Seiten');
                $active_extensions[] = ['name' => $ext['name'], 'scope' => $scope_label, 'key' => $key];
            }
        }
        
        echo '<div class="wrap"><h1>' . esc_html__( 'Erweiterungen', 'postindexer' ) . '</h1>';
        
        // Toolbar mit aktiven Erweiterungen
        echo '<div class="ps-extensions-toolbar">';
        echo '<div class="ps-toolbar-left">';
        echo '<span class="ps-toolbar-count"><strong>' . $active_count . '</strong> aktive Erweiterung' . ($active_count !== 1 ? 'en' : '') . '</span>';
        echo '</div>';
        echo '<div class="ps-toolbar-right">';
        if (!empty($active_extensions)) {
            foreach ($active_extensions as $ext) {
                echo '<span class="ps-active-badge" data-extkey="' . esc_attr($ext['key']) . '">';
                echo '<span class="badge-name">' . esc_html($ext['name']) . '</span>';
                echo '<span class="badge-scope">' . esc_html($ext['scope']) . '</span>';
                echo '</span>';
            }
        }
        echo '</div>';
        echo '</div>';
        
        if (!$comment_indexer_active) {
            echo '<div style="max-width:100%;margin:1.5em 0;padding:1.2em 1.8em;background:#fffbe6;border:1.5px solid #ffe58f;border-radius:8px;display:flex;align-items:center;gap:1em;">';
            echo '<span style="font-size:1.8em;color:#f1c40f;">&#9888;&#65039;</span>';
            echo '<div style="flex:1;">';
            echo '<strong style="font-size:1.1em;color:#b8860b;">' . esc_html__('Comment Indexer ist aktuell deaktiviert', 'postindexer') . '</strong> – ';
            echo esc_html__('Erweiterungen, die darauf basieren, können nicht genutzt werden.', 'postindexer');
            echo ' <a href="' . esc_url(network_admin_url('admin.php?page=comment-index')) . '" style="font-weight:bold;">' . esc_html__('Jetzt aktivieren', 'postindexer') . '</a>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '<style>
        .ps-extensions-toolbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 1.2em 1.8em;
            border-radius: 10px;
            margin-bottom: 2em;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1em;
            box-shadow: 0 4px 16px rgba(102,126,234,0.2);
        }
        .ps-toolbar-count {
            font-size: 1.15em;
        }
        .ps-toolbar-count strong {
            font-size: 1.5em;
            margin-right: 0.2em;
        }
        .ps-toolbar-right {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6em;
        }
        .ps-active-badge {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            padding: 0.4em 1em;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 0.6em;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ps-active-badge:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .badge-name {
            font-weight: 600;
        }
        .badge-scope {
            background: rgba(255,255,255,0.3);
            padding: 0.2em 0.6em;
            border-radius: 10px;
            font-size: 0.85em;
        }
        .ps-extensions-list {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .ps-extension-item {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .ps-extension-item:last-child {
            border-bottom: none;
        }
        .ps-extension-item:hover {
            background: #fafafa;
        }
        .ps-extension-header {
            padding: 1.2em 1.5em;
            display: flex;
            align-items: center;
            gap: 1.5em;
            cursor: pointer;
            user-select: none;
        }
        .ps-extension-toggle {
            flex-shrink: 0;
        }
        .ps-extension-info {
            flex: 1;
        }
        .ps-extension-title {
            font-size: 1.1em;
            font-weight: 600;
            margin: 0 0 0.3em 0;
            color: #333;
        }
        .ps-extension-desc {
            font-size: 0.95em;
            color: #666;
            margin: 0;
        }
        .ps-extension-status {
            display: flex;
            align-items: center;
            gap: 1em;
            flex-shrink: 0;
        }
        .ps-status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #aaa;
        }
        .ps-status-indicator.active {
            background: #2ecc40;
            box-shadow: 0 0 8px rgba(46,204,64,0.5);
        }
        .ps-expand-btn {
            background: #0073aa;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 0.5em 1.2em;
            font-size: 0.9em;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        .ps-expand-btn:hover {
            background: #005177;
            transform: scale(1.05);
        }
        .ps-expand-btn.expanded {
            background: #d63638;
        }
        .ps-expand-btn.expanded:hover {
            background: #b32d2e;
        }
        .ps-extension-options {
            display: none;
            padding: 1.5em;
            background: #f9f9f9;
            border-top: 1px solid #e5e5e5;
        }
        .ps-extension-options.expanded {
            display: block;
        }
        .ps-option-group {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 1.2em;
            margin-bottom: 1.2em;
        }
        .ps-option-group h4 {
            margin: 0 0 1em 0;
            font-size: 1.05em;
            color: #23282d;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 0.5em;
        }
        .ps-scope-radios {
            display: flex;
            gap: 1.5em;
            margin: 0.8em 0;
            flex-wrap: wrap;
        }
        .ps-scope-radios label {
            display: flex;
            align-items: center;
            gap: 0.5em;
            cursor: pointer;
            font-size: 0.95em;
        }
        .ps-scope-sites {
            margin-top: 1em;
            display: none;
        }
        .ps-scope-sites.visible {
            display: block;
        }
        .ps-scope-sites select {
            width: 100%;
            max-width: 400px;
            padding: 0.6em;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .ps-extension-save-btn {
            background: linear-gradient(90deg, #00c3ff 0%, #005bea 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0.8em 2.5em;
            font-size: 1.05em;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(0,91,234,0.2);
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1em;
        }
        .ps-extension-save-btn:hover {
            background: linear-gradient(90deg, #005bea 0%, #00c3ff 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,91,234,0.3);
        }
        .ps-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        .ps-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .ps-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 26px;
        }
        .ps-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .ps-slider {
            background-color: #2ecc40;
        }
        input:checked + .ps-slider:before {
            transform: translateX(24px);
        }
        .ps-required-notice {
            color: #d63638;
            font-weight: 600;
            padding: 0.8em 1em;
            background: #fff8f8;
            border: 1px solid #fdd;
            border-radius: 4px;
            margin-top: 1em;
        }
        </style>';
        echo '<div class="ps-extensions-list">';
        foreach ($this->extensions as $key => $ext) {
            $scope = $settings[$key]['scope'] ?? 'main';
            $selected_sites = $settings[$key]['sites'] ?? [];
            $active = isset($settings[$key]['active']) ? (int)$settings[$key]['active'] : 0;
            $disabled = (!empty($ext['requires_comment_indexer']) && !$comment_indexer_active);
            
            echo '<div class="ps-extension-item" data-extkey="' . esc_attr($key) . '">';
            echo '<div class="ps-extension-header">';
            
            // Toggle Switch
            echo '<div class="ps-extension-toggle">';
            echo '<label class="ps-switch">';
            echo '<input type="checkbox" class="extension-active-toggle" data-key="' . esc_attr($key) . '" ' . ($active ? 'checked' : '') . ' ' . ($disabled ? 'disabled' : '') . '>';
            echo '<span class="ps-slider"></span>';
            echo '</label>';
            echo '</div>';
            
            // Info
            echo '<div class="ps-extension-info">';
            echo '<h3 class="ps-extension-title">' . esc_html($ext['name']) . '</h3>';
            echo '<p class="ps-extension-desc">' . esc_html($ext['desc']) . '</p>';
            echo '</div>';
            
            // Status
            echo '<div class="ps-extension-status">';
            echo '<span class="ps-status-indicator ' . ($active ? 'active' : '') . '"></span>';
            echo '<button type="button" class="ps-expand-btn" data-key="' . esc_attr($key) . '">';
            echo '<span class="expand-text">Optionen anzeigen</span>';
            echo '</button>';
            echo '</div>';
            
            echo '</div>'; // .ps-extension-header
            
            // Options Panel
            echo '<div class="ps-extension-options" data-key="' . esc_attr($key) . '">';
            echo '<form method="post" class="ps-extension-form">';
            wp_nonce_field('ps_extension_settings_save_' . $key, 'ps_extension_settings_nonce_' . $key);
            
            // Hidden field für aktiv-Status
            echo '<input type="hidden" name="ps_extensions_active[' . $key . ']" class="hidden-active-field" value="' . ($active ? '1' : '0') . '">';
            
            echo '<div class="ps-option-group">';
            echo '<h4>Aktivierungsbereich</h4>';
            echo '<div class="ps-scope-radios">';
            echo '<label><input type="radio" name="ps_extensions_scope[' . $key . ']" value="network" ' . checked($scope, 'network', false) . ' ' . ($disabled ? 'disabled' : '') . '> Netzwerkweit</label>';
            echo '<label><input type="radio" name="ps_extensions_scope[' . $key . ']" value="main" ' . checked($scope, 'main', false) . ' ' . ($disabled ? 'disabled' : '') . '> Nur Hauptseite</label>';
            echo '<label><input type="radio" name="ps_extensions_scope[' . $key . ']" value="sites" ' . checked($scope, 'sites', false) . ' ' . ($disabled ? 'disabled' : '') . '> Bestimmte Seiten</label>';
            echo '</div>';
            
            $display_sites = ($scope === 'sites') ? 'visible' : '';
            echo '<div class="ps-scope-sites ' . $display_sites . '" data-key="' . esc_attr($key) . '">';
            echo '<select name="ps_extensions_sites[' . $key . '][]" multiple size="6" ' . ($disabled ? 'disabled' : '') . '>';
            foreach ($sites as $site_id) {
                $blog_details = get_blog_details($site_id);
                $sel = in_array($site_id, $selected_sites) ? 'selected' : '';
                echo '<option value="' . $site_id . '" ' . $sel . '>' . esc_html($blog_details->blogname) . ' (ID ' . $site_id . ')</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '</div>'; // .ps-option-group
            
            if ($disabled) {
                echo '<div class="ps-required-notice">⚠ Diese Erweiterung benötigt den Comment Indexer</div>';
            }
            
            // Erweiterungsspezifische Einstellungen
            $settings_html = $this->get_extension_settings_html($key, $comment_indexer_active);
            if (!empty($settings_html)) {
                echo '<div class="ps-option-group">';
                echo '<h4>Erweiterte Einstellungen</h4>';
                echo $settings_html;
                echo '</div>';
            }
            
            echo '<button type="submit" class="ps-extension-save-btn">Einstellungen speichern</button>';
            echo '</form>';
            echo '</div>'; // .ps-extension-options
            
            echo '</div>'; // .ps-extension-item
        }
        echo '</div>'; // .ps-extensions-list
        
        // JavaScript für Interaktionen
        echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle active switch
    document.querySelectorAll('.extension-active-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var key = this.dataset.key;
            var item = document.querySelector('.ps-extension-item[data-extkey=\"' + key + '\"]');
            var indicator = item.querySelector('.ps-status-indicator');
            var hiddenField = item.querySelector('.hidden-active-field');
            
            if (this.checked) {
                indicator.classList.add('active');
                if (hiddenField) hiddenField.value = '1';
            } else {
                indicator.classList.remove('active');
                if (hiddenField) hiddenField.value = '0';
            }
        });
    });
    
    // Expand/Collapse options
    document.querySelectorAll('.ps-expand-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var key = this.dataset.key;
            var options = document.querySelector('.ps-extension-options[data-key=\"' + key + '\"]');
            var text = this.querySelector('.expand-text');
            
            if (options.classList.contains('expanded')) {
                options.classList.remove('expanded');
                this.classList.remove('expanded');
                text.textContent = 'Optionen anzeigen';
            } else {
                // Schließe alle anderen
                document.querySelectorAll('.ps-extension-options').forEach(function(opt) {
                    opt.classList.remove('expanded');
                });
                document.querySelectorAll('.ps-expand-btn').forEach(function(b) {
                    b.classList.remove('expanded');
                    b.querySelector('.expand-text').textContent = 'Optionen anzeigen';
                });
                
                // Öffne diese
                options.classList.add('expanded');
                this.classList.add('expanded');
                text.textContent = 'Optionen schließen';
                
                // Scroll zu Options
                setTimeout(function() {
                    options.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        });
    });
    
    // Scope radio buttons - show/hide sites select
    document.querySelectorAll('input[name^=\"ps_extensions_scope\"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var name = this.name;
            var key = name.match(/\\[(\\w+)\\]/)[1];
            var sitesDiv = document.querySelector('.ps-scope-sites[data-key=\"' + key + '\"]');
            
            if (this.value === 'sites' && this.checked) {
                sitesDiv.classList.add('visible');
            } else if (sitesDiv) {
                sitesDiv.classList.remove('visible');
            }
        });
    });
    
    // Click on badge in toolbar - scroll to extension and expand
    document.querySelectorAll('.ps-active-badge').forEach(function(badge) {
        badge.addEventListener('click', function() {
            var key = this.dataset.extkey;
            var item = document.querySelector('.ps-extension-item[data-extkey=\"' + key + '\"]');
            var btn = item ? item.querySelector('.ps-expand-btn[data-key=\"' + key + '\"]') : null;
            
            if (item && btn) {
                item.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setTimeout(function() {
                    btn.click();
                }, 300);
            }
        });
    });
});
</script>";
        
        // Nach dem Speichern: Setup für Netzwerk Seiten-Tags erzwingen, wenn aktiviert
        if (isset($settings['global_site_tags']['active']) && $settings['global_site_tags']['active']) {
            if (!class_exists('globalsitetags')) {
                require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
            }
            if (class_exists('globalsitetags')) {
                $gst = new \globalsitetags();
                if (method_exists($gst, 'force_setup')) {
                    $gst->force_setup();
                }
            }
        }
    }

    private function get_extension_settings_html($key, $comment_indexer_active) {
        $settings_html = '';
        
        if ($key === 'recent_network_posts' && class_exists('Recent_Network_Posts')) {
            $recent = new \Recent_Network_Posts();
            $settings_html = $recent->render_settings_form();
        } elseif ($key === 'global_site_search' && class_exists('Global_Site_Search_Settings_Renderer')) {
            $gss = new \Global_Site_Search_Settings_Renderer();
            $settings_html = $gss->render_settings_form();
        } elseif ($key === 'global_site_tags') {
            require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
            if (class_exists('Global_Site_Tags_Settings_Renderer')) {
                $gst = new \Global_Site_Tags_Settings_Renderer();
                $settings_html = $gst->render_settings_form();
            }
        } elseif ($key === 'recent_global_posts_widget') {
            require_once dirname(__DIR__) . '/includes/recent-global-posts-widget/settings.php';
            if (class_exists('Recent_Global_Posts_Widget_Settings_Renderer')) {
                $rgpw = new \Recent_Global_Posts_Widget_Settings_Renderer();
                $settings_html = $rgpw->render_settings_form();
            }
        } elseif ($key === 'live_stream_widget') {
            require_once dirname(__DIR__) . '/includes/live-stream-widget/settings.php';
            if (class_exists('Live_Stream_Widget_Settings_Renderer')) {
                $lsw = new \Live_Stream_Widget_Settings_Renderer();
                $settings_html = $lsw->render_settings_form();
            }
        } elseif ($key === 'recent_global_comments_widget') {
            require_once dirname(__DIR__) . '/includes/recent-global-comments-widget/settings.php';
            if (class_exists('Recent_Global_Comments_Widget_Settings_Renderer')) {
                $rgcw = new \Recent_Global_Comments_Widget_Settings_Renderer();
                $settings_html = $rgcw->render_settings_form();
            }
        } elseif ($key === 'recent_comments') {
            require_once dirname(__DIR__) . '/includes/recent-comments/settings.php';
            if (class_exists('Recent_Comments_Settings_Renderer')) {
                $rcw = new \Recent_Comments_Settings_Renderer();
                $settings_html = $rcw->render_settings_form($comment_indexer_active);
            }
        } elseif ($key === 'recent_global_author_comments_feed') {
            require_once dirname(__DIR__) . '/includes/recent-global-author-comments-feed/settings.php';
            if (class_exists('Recent_Global_Author_Comments_Feed_Settings_Renderer')) {
                $gacf = new \Recent_Global_Author_Comments_Feed_Settings_Renderer();
                $settings_html = $gacf->render_settings_form();
            }
        } elseif ($key === 'comment_form_text') {
            require_once dirname(__DIR__) . '/includes/comment-form-text/comment-form-text.php';
            if (class_exists('Comment_Form_Text_Settings_Renderer')) {
                $cft = new \Comment_Form_Text_Settings_Renderer();
                $settings_html = $cft->render_settings_form();
            }
        } elseif ($key === 'recent_global_author_posts_feed') {
            require_once dirname(__DIR__) . '/includes/recent-global-author-posts-feed/settings.php';
            if (class_exists('Recent_Global_Author_Posts_Feed_Settings_Renderer')) {
                $rgapf = new \Recent_Global_Author_Posts_Feed_Settings_Renderer();
                $settings_html = $rgapf->render_settings_form();
            }
        } elseif ($key === 'recent_global_comments_feed') {
            require_once dirname(__DIR__) . '/includes/recent-global-comments-feed/settings.php';
            if (class_exists('Recent_Global_Comments_Feed_Settings_Renderer')) {
                $rgcf = new \Recent_Global_Comments_Feed_Settings_Renderer();
                $settings_html = $rgcf->render_settings_form();
            }
        } elseif ($key === 'recent_global_posts_feed') {
            require_once dirname(__DIR__) . '/includes/recent-global-posts-feed/settings.php';
            if (class_exists('Recent_Global_Posts_Feed_Settings_Renderer')) {
                $rgpf = new \Recent_Global_Posts_Feed_Settings_Renderer();
                $settings_html = $rgpf->render_settings_form();
            }
        }
        
        // Bereinige HTML - entferne Form-Tags und Submit-Buttons
        if (!empty($settings_html)) {
            $settings_html = preg_replace('#^\s*<form[^>]*>#is', '', $settings_html);
            $settings_html = preg_replace('#<input[^>]+type=["\']?submit["\']?[^>]*>#is', '', $settings_html);
            $settings_html = preg_replace('#<button[^>]+type=["\']?submit["\']?[^>]*>.*?</button>#is', '', $settings_html);
            $settings_html = preg_replace('#</form>\s*$#is', '', $settings_html);
        }
        
        return $settings_html;
    }

    public function get_settings() {
        $settings = get_site_option($this->option_name, []);
        // Migration: Wenn recent_comments noch keine Settings hat, aber recent_global_comments_widget schon, dann verschiebe sie dauerhaft
        if (!isset($settings['recent_comments']) && isset($settings['recent_global_comments_widget'])) {
            $settings['recent_comments'] = $settings['recent_global_comments_widget'];
            unset($settings['recent_global_comments_widget']);
            update_site_option($this->option_name, $settings); // dauerhaft speichern
        }
        // Defaults für neue Erweiterungen
        foreach ($this->extensions as $key => $ext) {
            if (!isset($settings[$key]['scope'])) $settings[$key]['scope'] = 'main';
            if (!isset($settings[$key]['sites'])) $settings[$key]['sites'] = [];
            if (!isset($settings[$key]['active'])) $settings[$key]['active'] = 0; // Standard: inaktiv
        }
        return $settings;
    }

    // Erweiterte Aktivierungslogik: Ist die Erweiterung aktiviert UND für diese Seite freigegeben?
    public function is_extension_active_for_site($extension_key, $site_id = null) {
        if (!$site_id) $site_id = get_current_blog_id();
        $settings = $this->get_settings();
        $scope = $settings[$extension_key]['scope'] ?? 'main';
        $active = isset($settings[$extension_key]['active']) ? (int)$settings[$extension_key]['active'] : 1;
        $main_site = function_exists('get_main_site_id') ? get_main_site_id() : 1;
        if (!$active) return false;
        if ($scope === 'network') return true;
        if ($scope === 'main') return $site_id == $main_site;
        if ($scope === 'sites') return in_array($site_id, $settings[$extension_key]['sites'] ?? []);
        return false;
    }

    // Hilfsfunktion: Gibt den passenden Settings-Renderer-Key für Netzwerk-Kommentare zurück
    private function get_recent_comments_settings_key() {
        // Bevorzuge neuen Key, aber unterstütze Fallback auf alten
        if (isset($this->extensions['recent_comments'])) {
            return 'recent_comments';
        } elseif (isset($this->extensions['recent_global_comments_widget'])) {
            return 'recent_global_comments_widget';
        }
        return null;
    }
}

}

if ( !class_exists('Recent_Network_Posts') ) {
    require_once dirname(__DIR__) . '/includes/recent-global-posts/recent-posts.php';
}
if ( !class_exists('Global_Site_Search_Settings_Renderer') ) {
    require_once dirname(__DIR__) . '/includes/global-site-search/global-site-search.php';
}
if ( !class_exists('Global_Site_Tags_Settings_Renderer') ) {
    require_once dirname(__DIR__) . '/includes/global-site-tags/global-site-tags.php';
}
if ( !class_exists('Live_Stream_Widget_Settings_Renderer') ) {
    require_once dirname(__DIR__) . '/includes/live-stream-widget/live-stream.php';
}
