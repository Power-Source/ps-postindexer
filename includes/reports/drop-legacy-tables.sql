-- =========================================================
-- Post Indexer: Drop Legacy Activity Tables
-- =========================================================
-- Datum: 11. Januar 2026
-- Zweck: Entfernt veraltete Activity-Tabellen
-- Grund: Reports nutzen jetzt direkt den Post Index
--
-- WICHTIG: Ersetze 'wp_' mit deinem Datenbank-Präfix!
-- =========================================================

-- Zeige Tabellen-Größen vor dem Löschen
SELECT 
    table_name AS "Tabelle",
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Größe (MB)",
    table_rows AS "Zeilen"
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
AND table_name IN (
    'wp_reports_user_activity',
    'wp_reports_post_activity',
    'wp_reports_comment_activity',
    'wp_reports_page_activity'
)
ORDER BY (data_length + index_length) DESC;

-- Lösche die Tabellen
DROP TABLE IF EXISTS `wp_reports_user_activity`;
DROP TABLE IF EXISTS `wp_reports_post_activity`;
DROP TABLE IF EXISTS `wp_reports_comment_activity`;
DROP TABLE IF EXISTS `wp_reports_page_activity`;

-- Bestätigung
SELECT 'Legacy Activity Tables wurden erfolgreich gelöscht!' AS "Status";
