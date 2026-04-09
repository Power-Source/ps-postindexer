# Legacy Widgets Refactor Plan

Ziel: Die verbliebenen Legacy-Widgets technisch vereinheitlichen, wartbarer machen und sauber an den Postindexer koppeln.

## Scope

Betroffene Widget-Module (Prioritaet):
- `includes/recent-global-comments-widget/recent-global-comments-widget.php`
- `includes/recent-global-comments-feed/recent-global-comments-feed.php`
- `includes/recent-global-posts-feed/widget-recent-global-posts-feed.php`
- `includes/recent-global-posts-widget/widget-recent-global-posts.php`
- `includes/live-stream-widget/live-stream.php` (nur Widget-Registrierung/-Settings)

Nicht im ersten Schritt:
- Feature-Logik, SQL und Feed-Protokolle
- Grosse UI-Neuentwuerfe

## Zielarchitektur

1. Einheitliches Bootstrap pro Widget-Modul
- Je Modul eine kleine Loader-Funktion mit klarer Reihenfolge:
  - Pruefen, ob Abhaengigkeiten geladen sind (`Network_Query`, Klassen, Funktionen)
  - Pruefen, ob Erweiterung fuer aktuelle Site aktiv ist
  - Widget registrieren
- Keine Registrierung in mehreren Hooks fuer dasselbe Widget.

2. Einheitliches Aktivierungs-Gate
- Gemeinsame Helper-Funktion (z. B. `ps_postindexer_is_extension_enabled($key, $site_id = null)`), die:
  - erst `Postindexer_Extensions_Admin` nutzt,
  - sonst auf `postindexer_extensions_settings` faellt.
- Damit keine harte Abhaengigkeit auf globale Admin-Instanzen.

3. Modernes `WP_Widget`-Pattern
- Nur `__construct()` statt alte Konstruktor-Namen.
- Einheitliche Methoden:
  - `widget($args, $instance)`
  - `form($instance)`
  - `update($new_instance, $old_instance)`
- Ueberall `wp_parse_args()` + harte Defaults.

4. Einheitliche Sanitization/Output-Sicherheit
- In `update()`:
  - `sanitize_text_field`, `absint`, `in_array` fuer Enum-Felder.
- In `widget()`:
  - `esc_html`, `esc_url`, `esc_attr` fuer Ausgaben.
- Keine ungefilterten Instanzwerte direkt in HTML.

5. i18n-Standard
- Textdomain nur `postindexer`.
- Alle Labels/Strings ueber `__`, `_e`, `esc_html__`, `esc_html_e`.

## Umsetzungsphasen

### Phase 1: Infrastruktur (low risk)
- Gemeinsamen Activation-Helper im Pluginkern anlegen (z. B. `includes/functions.php`).
- Bestehende Widgets schrittweise auf den Helper umstellen.
- Doppelte/konkurrierende `widgets_init`-Registrierungen entfernen.

### Phase 2: Widget-Basis vereinheitlichen
- Je Widget `update()` nachziehen (wo noch nicht vorhanden).
- Defaults zentral in Klasse als Konstante/Property.
- Wiederkehrende Form-Felder (Anzahl, Titel, Avatare) angleichen.

### Phase 3: Legacy APIs abbauen
- Alte prozedurale `wp_register_sidebar_widget`/`wp_register_widget_control` Varianten auf `WP_Widget`-Klassen umstellen.
- Rueckwaertskompatible Wrapper fuer alte Hook-Namen nur falls noetig.

### Phase 4: Tests und Regression
- Funktionscheck pro Scope:
  - `network`, `main`, `sites`
- Leerdatenfall (keine Posts/Kommentare) fuer jedes Widget pruefen.
- Multisite-Switching und `Network_Query`-Abhaengigkeiten validieren.

## Abnahmekriterien

- Jedes Widget hat genau einen klaren Registrierungsweg.
- Keine globale Admin-Instanz als zwingende Voraussetzung.
- Alle Widget-Instanzfelder werden validiert/sanitized gespeichert.
- Alle Frontend-Ausgaben sind escaped.
- Einheitliche Textdomain `postindexer` im gesamten Widget-Bereich.

## Konkrete Reihenfolge (empfohlen)

1. `recent-global-comments-widget`
2. `recent-global-comments-feed`
3. `recent-global-posts-feed`
4. `live-stream-widget` (Widget-Anteil)

Diese Reihenfolge minimiert Risiko, weil zuerst die aelteren prozeduralen Widgets modernisiert werden.
