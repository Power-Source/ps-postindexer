# 🗑️ Migration: Activity-Tabellen entfernt

**Datum:** 11. Januar 2026  
**Status:** ✅ ABGESCHLOSSEN

## Zusammenfassung

Die veralteten Activity-Tabellen wurden vollständig aus dem Plugin entfernt:
- ❌ `reports_user_activity`
- ❌ `reports_post_activity`
- ❌ `reports_comment_activity`
- ❌ `reports_page_activity`

**Grund:** Reports nutzen jetzt direkt den **Post Index** (network_posts) über die neue `Reports_Data_Source` Klasse.

---

## Was wurde geändert?

### 1. **Tabellen-Erstellung entfernt**

**Dateien:**
- [post-indexer.php](../../post-indexer.php) - Zeilen 155-210
- [reports.php](reports.php) - Zeilen 80-140

**Vorher:**
```php
maybe_create_table( "{$wpdb->base_prefix}reports_post_activity", ... );
maybe_create_table( "{$wpdb->base_prefix}reports_comment_activity", ... );
// etc.
```

**Nachher:**
```php
// DEPRECATED: Activity-Tabellen wurden entfernt
// Reports nutzen jetzt direkt den Post Index (network_posts)
// Siehe: includes/reports/class-reports-data-source.php
```

### 2. **Activity-Logging-Methoden entfernt**

**Datei:** [reports.php](reports.php)

**Entfernte Methoden:**
- `comment_activity()` - Loggte Comment-Aktivität in DB
- `comment_activity_remove()` - Entfernte Comment-Einträge
- `comment_activity_remove_blog()` - Entfernte Blog-Comments
- `post_activity()` - Loggte Post-Aktivität in DB
- `post_activity_remove()` - Entfernte Post-Einträge
- `post_activity_remove_blog()` - Entfernte Blog-Posts

**Grund:** Diese Methoden wurden von keinen Hooks mehr aufgerufen (Hooks wurden bereits entfernt bei Data Source Integration).

### 3. **Migration-Skript erstellt**

**Datei:** [drop-legacy-tables.php](drop-legacy-tables.php)

Dieses Skript kann verwendet werden, um die alten Tabellen aus bestehenden Installationen zu entfernen.

---

## Bestehende Installationen migrieren

Wenn das Plugin bereits installiert ist und alte Activity-Tabellen existieren, können diese mit dem Migration-Skript entfernt werden:

### Option 1: WP-CLI (empfohlen)

```bash
wp eval-file wp-content/plugins/ps-postindexer/includes/reports/drop-legacy-tables.php
```

### Option 2: Manuell via Code

Füge temporär diese Zeile in [post-indexer.php](../../post-indexer.php) ein:

```php
// Nur einmalig ausführen!
require_once( __DIR__ . '/includes/reports/drop-legacy-tables.php' );
postindexer_drop_legacy_activity_tables();
```

Rufe dann eine beliebige Admin-Seite auf. Die Tabellen werden automatisch gelöscht.  
**Wichtig:** Entferne die Zeilen danach wieder!

### Option 3: SQL direkt

```sql
DROP TABLE IF EXISTS wp_reports_user_activity;
DROP TABLE IF EXISTS wp_reports_post_activity;
DROP TABLE IF EXISTS wp_reports_comment_activity;
DROP TABLE IF EXISTS wp_reports_page_activity;
```

*(Ersetze `wp_` mit deinem Datenbank-Präfix)*

---

## Vorteile der Änderung

### ✅ **Performance**
- Keine doppelten INSERT-Queries mehr bei jedem Post/Comment
- Weniger DB-Overhead
- Schnellere Report-Generierung (nutzt bereits indexierte Daten)

### ✅ **Speicherplatz**
Je nach Netzwerk-Größe können **mehrere GB** an DB-Speicher frei werden:
- Große Netzwerke: ~2-5 GB
- Mittlere Netzwerke: ~500 MB - 2 GB
- Kleine Netzwerke: ~50-500 MB

### ✅ **Konsistenz**
- Single Source of Truth: Nur noch Post Index
- Reports zeigen exakt die gleichen Daten wie der Index
- Keine Synchronisations-Probleme mehr

### ✅ **Wartbarkeit**
- Weniger Code zu maintainen
- Einfachere Architektur
- Klare Trennung: Index = Datenquelle, Reports = Darstellung

---

## Technische Details

### Datenfluss (alt)

```
Post erstellt → save_post Hook
    ↓
post_activity() Methode
    ↓
INSERT INTO reports_post_activity
    ↓
Reports lesen aus reports_post_activity
```

**Problem:** Daten wurden doppelt gespeichert (network_posts + reports_post_activity)

### Datenfluss (neu)

```
Post erstellt → Post Indexer
    ↓
INSERT/UPDATE network_posts
    ↓
Reports lesen direkt aus network_posts via Reports_Data_Source
```

**Vorteil:** Single Source of Truth, keine Duplikation

---

## Rückwärtskompatibilität

### ⚠️ Breaking Changes

Falls externe Code diese Methoden direkt aufruft:
- `$activity_reports->post_activity()`
- `$activity_reports->comment_activity()`
- etc.

Diese Aufrufe funktionieren nicht mehr. Stattdessen:

```php
// Alt (funktioniert nicht mehr):
global $activity_reports;
$activity_reports->post_activity( $post_id );

// Neu (verwende direkt den Index):
// Der Post Indexer speichert Posts automatisch
// Keine manuelle Activity-Logging nötig!
```

### ✅ Templates bleiben kompatibel

Alle Report-Templates ([user_posts.php](reports-files/reports/user_posts.php), etc.) wurden bereits refaktoriert und nutzen jetzt `Reports_Data_Source`.

---

## Zusammenfassung

| Aspekt | Vorher | Nachher |
|--------|--------|---------|
| DB-Tabellen | 7 (Index + 4 Activity) | 3 (nur Index) |
| Code-Zeilen | ~200 (Activity-Logging) | ~10 (Kommentare) |
| DB-Queries pro Post | 2 (Index + Activity) | 1 (nur Index) |
| Speicherverbrauch | Hoch (Duplikation) | Niedrig (Single Source) |
| Performance | Langsamer | Schneller |
| Wartbarkeit | Komplex | Einfach |

---

**Migration erfolgreich!** 🎉

Die Activity-Tabellen sind Geschichte. Reports sind jetzt schneller, konsistenter und wartungsfreundlicher.
