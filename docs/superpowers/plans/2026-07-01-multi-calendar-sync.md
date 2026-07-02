# Multi-Calendar Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the plugin display and merge events from two or more Google Calendars instead of a single selected calendar.

**Architecture:** Store selected calendars as an array option (`gcal_tag_filter_calendar_ids`) with read-time fallback to the legacy single option. A shared static fetch method loops every selected calendar, merges the items (no de-duplication), and sorts them by start time. The admin UI becomes a checkbox list. Pure resolution/sort/sanitize logic is extracted into static helpers so it can be unit-tested without WordPress.

**Tech Stack:** PHP 7.4+, WordPress plugin APIs, google/apiclient. Tests: PHPUnit ^9 + Brain Monkey ^2 (mocks WP functions, no DB).

## Global Constraints

- PHP floor: `>=8.1` (corrected from the stale 7.4 claim — production `google/apiclient` already requires 8.1+; the plugin's version metadata was updated to match during Task 1). Modern PHP 8.1 syntax is permitted but not required; keep changes consistent with the surrounding code style.
- Text domain for all user-facing strings: `gcal-tag-filter`.
- New array option name (verbatim): `gcal_tag_filter_calendar_ids`.
- Legacy single option name (verbatim, left intact): `gcal_tag_filter_calendar_id`.
- No de-duplication of events (show-all is a product decision).
- Do not introduce per-calendar colors, calendar-source visitor filters, or dedup.
- Follow existing code style: 4-space indent, Yoda-free conditionals as in the file, WP escaping (`esc_html`, `esc_attr`), `error_log` for diagnostics.

---

## File Structure

- `composer.json` — add dev dependencies + `test` script.
- `phpunit.xml.dist` (create) — PHPUnit config.
- `tests/bootstrap.php` (create) — loads composer autoload (which autoloads plugin classes + Brain Monkey).
- `tests/SmokeTest.php` (create) — proves the harness runs.
- `tests/ResolveCalendarIdsTest.php` (create) — unit tests for resolution + sanitize.
- `tests/SortItemsByStartTest.php` (create) — unit tests for merge sort.
- `tests/CacheKeyPartTest.php` (create) — unit tests for cache key part.
- `includes/class-gcal-oauth.php` — new option constant, `resolve_calendar_ids()`, `get_selected_calendar_ids()`, `set_calendar_ids()`, `sanitize_calendar_ids()`, updated `get_selected_calendar_id()`.
- `includes/class-gcal-calendar.php` — `sort_items_by_start()`, `fetch_raw_items_from_calendars()`, refactored `fetch_events_from_api()`.
- `includes/class-gcal-cache.php` — `build_calendar_key_part()`, updated `generate_key()`.
- `includes/class-gcal-shortcode.php` — empty-selection guard.
- `gcal-tag-filter.php` — AJAX handler uses shared fetch + empty guard.
- `admin/class-gcal-admin.php` — checkbox list UI, array option registration, summary line.
- `uninstall.php` — delete new option.

---

### Task 1: Test harness

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `tests/SmokeTest.php`

**Interfaces:**
- Produces: a working `composer test` command that discovers and runs tests under `tests/`.

- [ ] **Step 1: Add dev dependencies**

Run:
```bash
composer require --dev --no-interaction phpunit/phpunit:^9 brain/monkey:^2
```
Expected: `composer.json` gains a `require-dev` block; `vendor/` updated. (The existing `post-update-cmd` that prunes Google services will run — that's fine.)

- [ ] **Step 2: Add a `test` script to composer.json**

In `composer.json`, inside the existing `"scripts"` object, add a `test` entry alongside the current `post-install-cmd`/`post-update-cmd` keys:
```json
        "test": "phpunit"
```
(Keep the existing scripts; just add this key.)

- [ ] **Step 3: Create phpunit.xml.dist**

Create `phpunit.xml.dist`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="unit">
            <directory suffix="Test.php">tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 4: Create tests/bootstrap.php**

Create `tests/bootstrap.php`:
```php
<?php
/**
 * PHPUnit bootstrap. Loads Composer autoload (Brain Monkey, PHPUnit, and the
 * plugin's classmap-autoloaded GCal_* classes).
 */
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
```

- [ ] **Step 5: Create a smoke test**

Create `tests/SmokeTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase {
    public function test_harness_runs() {
        $this->assertTrue( true );
    }
}
```

- [ ] **Step 6: Run the suite**

Run: `composer test`
Expected: PASS — 1 test, 1 assertion, `OK (1 test, 1 assertion)`.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist tests/bootstrap.php tests/SmokeTest.php
git commit -m "test: add PHPUnit + Brain Monkey harness"
```

---

### Task 2: Calendar ID resolution, migration, and uninstall

**Files:**
- Modify: `includes/class-gcal-oauth.php` (constants near line 31; accessor methods near lines 343-354)
- Modify: `uninstall.php` (options array near line 24-31)
- Create: `tests/ResolveCalendarIdsTest.php`

**Interfaces:**
- Produces:
  - `const GCal_OAuth::OPTION_CALENDAR_IDS = 'gcal_tag_filter_calendar_ids';`
  - `static GCal_OAuth::resolve_calendar_ids( $ids, $legacy_id ): array` — pure; returns `array<string>`.
  - `static GCal_OAuth::sanitize_calendar_ids( $raw ): array` — sanitizes an array of ids to a clean, de-duplicated `array<string>`.
  - `GCal_OAuth::get_selected_calendar_ids(): array` — reads options, applies resolution/migration.
  - `GCal_OAuth::set_calendar_ids( array $ids ): void`.
  - `GCal_OAuth::get_selected_calendar_id()` now returns the first selected id or `false`.

- [ ] **Step 1: Write failing tests for resolution + sanitize**

Create `tests/ResolveCalendarIdsTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class ResolveCalendarIdsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_returns_empty_array_when_nothing_configured() {
        $this->assertSame( array(), GCal_OAuth::resolve_calendar_ids( array(), false ) );
    }

    public function test_falls_back_to_legacy_single_id() {
        $this->assertSame(
            array( 'legacy@group.calendar.google.com' ),
            GCal_OAuth::resolve_calendar_ids( array(), 'legacy@group.calendar.google.com' )
        );
    }

    public function test_prefers_array_option_over_legacy() {
        $this->assertSame(
            array( 'a@x.com', 'b@x.com' ),
            GCal_OAuth::resolve_calendar_ids( array( 'a@x.com', 'b@x.com' ), 'legacy@x.com' )
        );
    }

    public function test_filters_empty_and_whitespace_entries() {
        $this->assertSame(
            array( 'a@x.com' ),
            GCal_OAuth::resolve_calendar_ids( array( '', '  ', 'a@x.com' ), false )
        );
    }

    public function test_sanitize_dedupes_and_drops_empties() {
        // sanitize_text_field is a WP function; stub as identity/trimmer.
        Functions\when( 'sanitize_text_field' )->alias( function ( $v ) {
            return trim( (string) $v );
        } );

        $this->assertSame(
            array( 'a@x.com', 'b@x.com' ),
            GCal_OAuth::sanitize_calendar_ids( array( ' a@x.com ', 'b@x.com', 'a@x.com', '' ) )
        );
    }

    public function test_sanitize_returns_empty_array_for_non_array() {
        $this->assertSame( array(), GCal_OAuth::sanitize_calendar_ids( 'not-an-array' ) );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test`
Expected: FAIL — `Error: Call to undefined method GCal_OAuth::resolve_calendar_ids()` (or similar undefined-method errors).

- [ ] **Step 3: Add the option constant**

In `includes/class-gcal-oauth.php`, in the constants block (after line 31, the `OPTION_CALENDAR_ID` line), add:
```php
    const OPTION_CALENDAR_IDS  = 'gcal_tag_filter_calendar_ids';
```

- [ ] **Step 4: Implement resolution, accessors, sanitize**

In `includes/class-gcal-oauth.php`, replace the existing `get_selected_calendar_id()` and `set_calendar_id()` methods (currently lines ~343-354) with:
```php
    /**
     * Resolve the configured calendar IDs, migrating from the legacy single
     * option when the array option is empty. Pure — no WordPress calls.
     *
     * @param mixed $ids       Value of the array option.
     * @param mixed $legacy_id Value of the legacy single option.
     * @return array List of calendar ID strings (may be empty).
     */
    public static function resolve_calendar_ids( $ids, $legacy_id ) {
        if ( is_array( $ids ) ) {
            $ids = array_values(
                array_filter(
                    array_map( 'trim', array_map( 'strval', $ids ) ),
                    function ( $id ) {
                        return $id !== '';
                    }
                )
            );
            if ( ! empty( $ids ) ) {
                return $ids;
            }
        }

        if ( ! empty( $legacy_id ) && is_string( $legacy_id ) ) {
            return array( $legacy_id );
        }

        return array();
    }

    /**
     * Sanitize a raw array of calendar IDs for storage.
     *
     * @param mixed $raw Raw submitted value.
     * @return array Clean, de-duplicated list of calendar ID strings.
     */
    public static function sanitize_calendar_ids( $raw ) {
        if ( ! is_array( $raw ) ) {
            return array();
        }

        $clean = array();
        foreach ( $raw as $id ) {
            $id = sanitize_text_field( $id );
            if ( $id !== '' ) {
                $clean[] = $id;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * Get the selected calendar IDs (array), applying legacy migration.
     *
     * @return array List of calendar ID strings (may be empty).
     */
    public function get_selected_calendar_ids() {
        return self::resolve_calendar_ids(
            get_option( self::OPTION_CALENDAR_IDS, array() ),
            get_option( self::OPTION_CALENDAR_ID, false )
        );
    }

    /**
     * Persist the selected calendar IDs.
     *
     * @param array $ids Calendar IDs.
     */
    public function set_calendar_ids( array $ids ) {
        update_option( self::OPTION_CALENDAR_IDS, self::sanitize_calendar_ids( $ids ) );
    }

    /**
     * Get the first selected calendar ID (backward-compatible single value).
     *
     * @return string|false
     */
    public function get_selected_calendar_id() {
        $ids = $this->get_selected_calendar_ids();
        return ! empty( $ids ) ? $ids[0] : false;
    }

    /**
     * Set a single selected calendar ID (backward-compatible).
     *
     * @param string $calendar_id Calendar ID.
     */
    public function set_calendar_id( $calendar_id ) {
        update_option( self::OPTION_CALENDAR_ID, sanitize_text_field( $calendar_id ) );
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer test`
Expected: PASS — all `ResolveCalendarIdsTest` tests green, smoke test still green.

- [ ] **Step 6: Add the new option to uninstall cleanup**

In `uninstall.php`, in the `$options` array (currently lines ~24-31), add the new option right after `'gcal_tag_filter_calendar_id',`:
```php
        'gcal_tag_filter_calendar_ids',
```

- [ ] **Step 7: Syntax-check modified PHP files**

Run: `php -l includes/class-gcal-oauth.php && php -l uninstall.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 8: Commit**

```bash
git add includes/class-gcal-oauth.php uninstall.php tests/ResolveCalendarIdsTest.php
git commit -m "feat: array-based calendar selection with legacy migration"
```

---

### Task 3: Merge, sort, and multi-calendar fetch

**Files:**
- Modify: `includes/class-gcal-calendar.php` (the paginated fetch inside `fetch_events_from_api()`, lines ~157-225)
- Create: `tests/SortItemsByStartTest.php`

**Interfaces:**
- Consumes: `GCal_OAuth::get_selected_calendar_ids()` (Task 2).
- Produces:
  - `static GCal_Calendar::sort_items_by_start( array $items ): array` — pure; sorts by start value ascending.
  - `static GCal_Calendar::fetch_raw_items_from_calendars( $service, array $calendar_ids, array $params ): array` — loops calendars, paginates, merges, sorts; throws `Exception` only when every calendar fails.

- [ ] **Step 1: Write failing tests for the sort helper**

Create `tests/SortItemsByStartTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;

class SortItemsByStartTest extends TestCase {

    /** Build a stub event whose getStart() returns an object with date/dateTime. */
    private function fakeEvent( array $start ) {
        return new class( $start ) {
            private $start;
            public function __construct( array $s ) {
                $this->start = (object) $s;
            }
            public function getStart() {
                return $this->start;
            }
        };
    }

    public function test_sorts_timed_events_ascending() {
        $late  = $this->fakeEvent( array( 'dateTime' => '2026-07-01T15:00:00Z' ) );
        $early = $this->fakeEvent( array( 'dateTime' => '2026-07-01T09:00:00Z' ) );

        $sorted = GCal_Calendar::sort_items_by_start( array( $late, $early ) );

        $this->assertSame( '2026-07-01T09:00:00Z', $sorted[0]->getStart()->dateTime );
        $this->assertSame( '2026-07-01T15:00:00Z', $sorted[1]->getStart()->dateTime );
    }

    public function test_merges_and_orders_across_calendars() {
        // Simulates two calendars' items concatenated then sorted.
        $items = array(
            $this->fakeEvent( array( 'dateTime' => '2026-07-03T10:00:00Z' ) ), // cal A
            $this->fakeEvent( array( 'date' => '2026-07-01' ) ),               // cal B all-day
            $this->fakeEvent( array( 'dateTime' => '2026-07-02T08:00:00Z' ) ), // cal A
        );

        $sorted = GCal_Calendar::sort_items_by_start( $items );

        $this->assertSame( '2026-07-01', $sorted[0]->getStart()->date );
        $this->assertSame( '2026-07-02T08:00:00Z', $sorted[1]->getStart()->dateTime );
        $this->assertSame( '2026-07-03T10:00:00Z', $sorted[2]->getStart()->dateTime );
    }

    public function test_empty_input_returns_empty() {
        $this->assertSame( array(), GCal_Calendar::sort_items_by_start( array() ) );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test`
Expected: FAIL — `Error: Call to undefined method GCal_Calendar::sort_items_by_start()`.

- [ ] **Step 3: Implement the sort + multi-calendar fetch helpers**

In `includes/class-gcal-calendar.php`, add these methods to the `GCal_Calendar` class (place them just before `fetch_events_from_api()`):
```php
    /**
     * Extract a sortable start value from an event item.
     *
     * @param object $item Google_Service_Calendar_Event (or compatible).
     * @return string Sortable start value ('' if unknown).
     */
    private static function get_item_start_value( $item ) {
        $start = $item->getStart();
        if ( ! empty( $start->dateTime ) ) {
            return $start->dateTime;
        }
        if ( ! empty( $start->date ) ) {
            return $start->date;
        }
        return '';
    }

    /**
     * Sort merged event items by start time ascending. Pure.
     *
     * @param array $items Event items.
     * @return array Sorted items.
     */
    public static function sort_items_by_start( array $items ) {
        usort(
            $items,
            function ( $a, $b ) {
                return strcmp( self::get_item_start_value( $a ), self::get_item_start_value( $b ) );
            }
        );
        return $items;
    }

    /**
     * Fetch and merge event items from every given calendar.
     *
     * Each calendar is paginated independently. Items are merged (no
     * de-duplication) and sorted by start time. If one calendar fails, the
     * error is logged and the others still return. Throws only when every
     * calendar fails.
     *
     * @param Google_Service_Calendar $service      Authenticated service.
     * @param array                   $calendar_ids Calendar IDs to fetch.
     * @param array                   $params       listEvents params (without pageToken).
     * @return array Merged, sorted event items.
     * @throws Exception When every calendar fetch fails.
     */
    public static function fetch_raw_items_from_calendars( $service, array $calendar_ids, array $params ) {
        $all_items = array();
        $errors    = array();
        $succeeded = 0;

        foreach ( $calendar_ids as $calendar_id ) {
            try {
                $page_token = null;
                $page_count = 0;

                do {
                    if ( $page_token ) {
                        $params['pageToken'] = $page_token;
                    } else {
                        unset( $params['pageToken'] );
                    }

                    $events    = $service->events->listEvents( $calendar_id, $params );
                    $all_items = array_merge( $all_items, $events->getItems() );
                    $page_token = $events->getNextPageToken();
                    $page_count++;
                } while ( $page_token && $page_count < 10 );

                $succeeded++;
            } catch ( Exception $e ) {
                $errors[] = $e->getMessage();
                error_log( 'GCal API Error for calendar ' . $calendar_id . ': ' . $e->getMessage() );
            }
        }

        if ( 0 === $succeeded && ! empty( $errors ) ) {
            throw new Exception( implode( '; ', $errors ) );
        }

        return self::sort_items_by_start( $all_items );
    }
```

- [ ] **Step 4: Run tests to verify the sort passes**

Run: `composer test`
Expected: PASS — `SortItemsByStartTest` green (the fetch helper is exercised manually later).

- [ ] **Step 5: Refactor fetch_events_from_api to use the shared fetch**

In `includes/class-gcal-calendar.php`, in `fetch_events_from_api()`:

Replace the single-id block (currently lines ~157-170):
```php
        $calendar_id = $this->oauth->get_selected_calendar_id();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Calendar ID: ' . ( $calendar_id ? $calendar_id : 'NONE' ) );
        }

        if ( ! $calendar_id ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'No calendar selected' );
            }
            return new WP_Error(
                'no_calendar',
                __( 'No calendar selected. Please select a calendar in the plugin settings.', 'gcal-tag-filter' )
            );
        }
```
with:
```php
        $calendar_ids = $this->oauth->get_selected_calendar_ids();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Calendar IDs: ' . ( ! empty( $calendar_ids ) ? implode( ', ', $calendar_ids ) : 'NONE' ) );
        }

        if ( empty( $calendar_ids ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'No calendar selected' );
            }
            return new WP_Error(
                'no_calendar',
                __( 'No calendar selected. Please select a calendar in the plugin settings.', 'gcal-tag-filter' )
            );
        }
```

Then replace the pagination block (currently lines ~202-225, from `// Fetch all pages of events` through `$items = $all_items;`) with:
```php
            // Fetch and merge events across all selected calendars.
            $items = self::fetch_raw_items_from_calendars( $service, $calendar_ids, $params );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Total events fetched: ' . count( $items ) . ' across ' . count( $calendar_ids ) . ' calendar(s)' );
            }
```
(Leave the sample-dates debug block and `return $items;` that follow unchanged. The removed block's own "Total events fetched" log is replaced by the line above; delete the duplicate that was inside the old block.)

- [ ] **Step 6: Syntax-check and run tests**

Run: `php -l includes/class-gcal-calendar.php && composer test`
Expected: `No syntax errors detected`; all tests PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/class-gcal-calendar.php tests/SortItemsByStartTest.php
git commit -m "feat: fetch and merge events across multiple calendars"
```

---

### Task 4: Cache key includes all calendars

**Files:**
- Modify: `includes/class-gcal-cache.php` (`generate_key()`, lines ~83-133)
- Create: `tests/CacheKeyPartTest.php`

**Interfaces:**
- Consumes: `GCal_OAuth::get_selected_calendar_ids()` (Task 2).
- Produces: `static GCal_Cache::build_calendar_key_part( array $ids ): string` — order-independent joined key part.

- [ ] **Step 1: Write failing tests for the key part**

Create `tests/CacheKeyPartTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;

class CacheKeyPartTest extends TestCase {

    public function test_is_order_independent() {
        $this->assertSame(
            GCal_Cache::build_calendar_key_part( array( 'a@x.com', 'b@x.com' ) ),
            GCal_Cache::build_calendar_key_part( array( 'b@x.com', 'a@x.com' ) )
        );
    }

    public function test_differs_for_different_selections() {
        $this->assertNotSame(
            GCal_Cache::build_calendar_key_part( array( 'a@x.com' ) ),
            GCal_Cache::build_calendar_key_part( array( 'a@x.com', 'b@x.com' ) )
        );
    }

    public function test_empty_selection_is_empty_string() {
        $this->assertSame( '', GCal_Cache::build_calendar_key_part( array() ) );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test`
Expected: FAIL — `Error: Call to undefined method GCal_Cache::build_calendar_key_part()`.

- [ ] **Step 3: Implement the key-part helper and use it in generate_key**

In `includes/class-gcal-cache.php`, add this method to the `GCal_Cache` class (just before `generate_key()`):
```php
    /**
     * Build an order-independent cache key part from selected calendar IDs.
     *
     * @param array $ids Calendar IDs.
     * @return string Sorted, comma-joined IDs.
     */
    public static function build_calendar_key_part( array $ids ) {
        sort( $ids );
        return implode( ',', $ids );
    }
```

Then in `generate_key()`, replace these two lines (currently ~84-85):
```php
        $oauth       = new GCal_OAuth();
        $calendar_id = $oauth->get_selected_calendar_id();
```
with:
```php
        $oauth        = new GCal_OAuth();
        $calendar_key = self::build_calendar_key_part( $oauth->get_selected_calendar_ids() );
```

And in the `$key_parts` array (currently ~120-125), replace `$calendar_id,` with `$calendar_key,`:
```php
        $key_parts = array(
            $calendar_key,
            $period,
            $date_key,
            implode( '_', $tags ),
        );
```

- [ ] **Step 4: Run tests + syntax check**

Run: `php -l includes/class-gcal-cache.php && composer test`
Expected: `No syntax errors detected`; all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add includes/class-gcal-cache.php tests/CacheKeyPartTest.php
git commit -m "feat: include all selected calendars in cache key"
```

---

### Task 5: AJAX handler and shortcode guard

**Files:**
- Modify: `gcal-tag-filter.php` (`gcal_ajax_fetch_events()`, lines ~355-378)
- Modify: `includes/class-gcal-shortcode.php` (guard at line ~112)

**Interfaces:**
- Consumes: `GCal_OAuth::get_selected_calendar_ids()`, `GCal_Calendar::fetch_raw_items_from_calendars()`.

- [ ] **Step 1: Update the AJAX fetch to loop calendars**

In `gcal-tag-filter.php`, inside `gcal_ajax_fetch_events()`, replace this block (currently lines ~360-378):
```php
        $client      = $oauth->get_authenticated_client();
        $calendar_id = $oauth->get_selected_calendar_id();

        if ( ! $client || ! $calendar_id ) {
            wp_send_json_error( array( 'message' => 'Not authenticated' ) );
            return;
        }

        $service = new Google_Service_Calendar( $client );
        $params  = array(
            'timeMin'      => $start->format( DateTime::RFC3339 ),
            'timeMax'      => $end->format( DateTime::RFC3339 ),
            'maxResults'   => 100,
            'singleEvents' => true,
            'orderBy'      => 'startTime',
        );

        $events = $service->events->listEvents( $calendar_id, $params );
        $items  = $events->getItems();
```
with:
```php
        $client       = $oauth->get_authenticated_client();
        $calendar_ids = $oauth->get_selected_calendar_ids();

        if ( ! $client || empty( $calendar_ids ) ) {
            wp_send_json_error( array( 'message' => 'Not authenticated' ) );
            return;
        }

        $service = new Google_Service_Calendar( $client );
        $params  = array(
            'timeMin'      => $start->format( DateTime::RFC3339 ),
            'timeMax'      => $end->format( DateTime::RFC3339 ),
            'maxResults'   => 100,
            'singleEvents' => true,
            'orderBy'      => 'startTime',
        );

        $items = GCal_Calendar::fetch_raw_items_from_calendars( $service, $calendar_ids, $params );
```
(The `$calendar` variable created earlier at line ~356 via `new GCal_Calendar()` is unused after this change — remove that line if present, since `fetch_raw_items_from_calendars` is static.)

- [ ] **Step 2: Update the shortcode empty-selection guard**

In `includes/class-gcal-shortcode.php`, replace the guard (currently lines ~112-118):
```php
        $calendar_id = $oauth->get_selected_calendar_id();

        if ( ! $calendar_id ) {
            return $this->display->render_error(
                __( 'No calendar selected. Please contact the site administrator.', 'gcal-tag-filter' )
            );
        }
```
with:
```php
        $calendar_ids = $oauth->get_selected_calendar_ids();

        if ( empty( $calendar_ids ) ) {
            return $this->display->render_error(
                __( 'No calendar selected. Please contact the site administrator.', 'gcal-tag-filter' )
            );
        }
```

- [ ] **Step 3: Syntax check**

Run: `php -l gcal-tag-filter.php && php -l includes/class-gcal-shortcode.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Run tests (no regressions)**

Run: `composer test`
Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add gcal-tag-filter.php includes/class-gcal-shortcode.php
git commit -m "feat: multi-calendar support in AJAX fetch and shortcode guard"
```

---

### Task 6: Admin checkbox UI

**Files:**
- Modify: `admin/class-gcal-admin.php` (`register_settings()` lines ~85-101; summary line ~279-286; selection form ~304-336)

**Interfaces:**
- Consumes: `GCal_OAuth::OPTION_CALENDAR_IDS`, `GCal_OAuth::sanitize_calendar_ids()`, `GCal_OAuth::get_selected_calendar_ids()`.

- [ ] **Step 1: Register the array option**

In `admin/class-gcal-admin.php`, in `register_settings()`, replace the first `register_setting(...)` call (the one for `OPTION_CALENDAR_ID`, lines ~86-93) with:
```php
        register_setting(
            'gcal_tag_filter_options',
            GCal_OAuth::OPTION_CALENDAR_IDS,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( 'GCal_OAuth', 'sanitize_calendar_ids' ),
                'default'           => array(),
            )
        );
```
(Leave the `GCal_Cache::OPTION_DURATION` registration below it unchanged.)

- [ ] **Step 2: Load the selected IDs for the view**

In `admin/class-gcal-admin.php`, in the settings page render method, find where `$selected_calendar = $this->oauth->get_selected_calendar_id();` is set (line ~203) and add below it:
```php
        $selected_calendars = $this->oauth->get_selected_calendar_ids();
```

- [ ] **Step 3: Update the "Selected Calendar" summary line**

Replace the summary block (currently lines ~279-286):
```php
                        <?php if ( $selected_calendar ) : ?>
                            <p>
                                <?php
                                /* translators: %s: Calendar name */
                                printf( esc_html__( 'Selected Calendar: <strong>%s</strong>', 'gcal-tag-filter' ), esc_html( $selected_calendar ) );
                                ?>
                            </p>
                        <?php endif; ?>
```
with:
```php
                        <?php if ( ! empty( $selected_calendars ) ) : ?>
                            <p>
                                <strong><?php esc_html_e( 'Selected Calendars:', 'gcal-tag-filter' ); ?></strong>
                                <?php echo esc_html( implode( ', ', $selected_calendars ) ); ?>
                            </p>
                        <?php endif; ?>
```

- [ ] **Step 4: Replace the single-select with a checkbox list**

Replace the `<select>` table cell (currently lines ~307-328, the `<table class="form-table">` … `</table>` block that contains `id="calendar_id"`) with:
```php
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <?php esc_html_e( 'Select Calendars', 'gcal-tag-filter' ); ?>
                                        </th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text">
                                                    <?php esc_html_e( 'Select Calendars', 'gcal-tag-filter' ); ?>
                                                </legend>
                                                <?php
                                                // Ensures the option is submitted even when nothing is checked,
                                                // allowing the selection to be cleared to empty.
                                                ?>
                                                <input type="hidden" name="<?php echo esc_attr( GCal_OAuth::OPTION_CALENDAR_IDS ); ?>[]" value="">
                                                <?php foreach ( $calendars as $calendar ) : ?>
                                                    <label style="display:block; margin-bottom:6px;">
                                                        <input type="checkbox"
                                                            name="<?php echo esc_attr( GCal_OAuth::OPTION_CALENDAR_IDS ); ?>[]"
                                                            value="<?php echo esc_attr( $calendar['id'] ); ?>"
                                                            <?php checked( in_array( $calendar['id'], $selected_calendars, true ) ); ?>>
                                                        <?php echo esc_html( $calendar['summary'] ); ?>
                                                        <?php echo $calendar['primary'] ? '(' . esc_html__( 'Primary', 'gcal-tag-filter' ) . ')' : ''; ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </fieldset>
                                            <p class="description">
                                                <?php esc_html_e( 'Choose one or more calendars to display events from.', 'gcal-tag-filter' ); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <?php submit_button( __( 'Save Calendar Selection', 'gcal-tag-filter' ) ); ?>
```
(Remove the old `submit_button( __( 'Save Calendar Selection', ... ) )` that followed the old table so it is not duplicated.)

- [ ] **Step 5: Syntax check**

Run: `php -l admin/class-gcal-admin.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Run tests (no regressions)**

Run: `composer test`
Expected: all tests PASS.

- [ ] **Step 7: Manual verification in WordPress**

In a WordPress install with the plugin connected to Google:
1. Open the plugin settings — the calendar selector shows checkboxes.
2. Check two calendars, Save. Reload — both remain checked; the summary lists both.
3. View a page with the `[gcal_tag_filter]` shortcode — events from both calendars appear, ordered by start time.
4. Put the same event on both calendars — it appears twice (show-all).
5. Uncheck all, Save — the calendar view shows "No calendar selected".
6. (Migration) On a site that only had the legacy single option set: before saving the new form, the previously-selected calendar still renders.

- [ ] **Step 8: Commit**

```bash
git add admin/class-gcal-admin.php
git commit -m "feat: checkbox multi-calendar selection in admin"
```

---

## Notes for the implementer

- The `google/apiclient` classes (`Google_Service_Calendar`, event objects) are autoloaded via Composer; unit tests never touch them (the sort test uses lightweight stub objects).
- `GCal_*` classes are classmap-autoloaded via `composer.json`, so tests can reference them without manual `require`.
- Brain Monkey is only needed for tests that call WordPress functions (`sanitize_text_field`). Pure-logic tests need no mocking.
- Do not add a WP upgrade routine — migration is intentionally read-time only (`resolve_calendar_ids` fallback).
