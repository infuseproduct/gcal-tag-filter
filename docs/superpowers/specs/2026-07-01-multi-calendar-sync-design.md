# Multi-Calendar Sync — Design

**Date:** 2026-07-01
**Status:** Approved, ready for planning

## Goal

Let the plugin display events from **two or more** Google Calendars at once, instead of a single selected calendar. Events from all selected calendars are merged into one stream and filtered by the existing global tag-based categories exactly as today.

## Decisions (from brainstorming)

- **Filter model:** Merge, keep tags as-is. All selected calendars' events are pooled into one stream; the existing tag/category filtering applies across all of them. Calendar source is invisible to the visitor.
- **Migration:** Auto-migrate. Existing single-calendar setting is carried forward with zero admin action.
- **Admin UI:** Checkbox list (one checkbox per available calendar).
- **Duplicates:** Show all. No de-duplication — if the same event exists on two selected calendars, it appears twice.

## Out of scope (YAGNI)

- Per-calendar colors or labels.
- A visitor-facing "filter by calendar source" dimension.
- De-duplication by `iCalUID`.

Tags/categories remain global across all calendars.

## Data model & migration

- New option: `gcal_tag_filter_calendar_ids` — an **array** of calendar ID strings.
- Existing option `gcal_tag_filter_calendar_id` (single string) is left untouched.

In `GCal_OAuth` (`includes/class-gcal-oauth.php`):

- Add `get_selected_calendar_ids()`:
  - Returns the array stored in `gcal_tag_filter_calendar_ids`.
  - **Fallback migration:** if that array is empty/unset but the legacy single option `gcal_tag_filter_calendar_id` has a value, return `[ legacy_id ]`.
  - Returns `array()` when nothing is configured.
- Add `set_calendar_ids( array $ids )`: sanitize each entry with `sanitize_text_field`, drop empties, `update_option`.
- Keep `get_selected_calendar_id()` returning the **first** selected calendar (used by the connection-test path). It should be backed by `get_selected_calendar_ids()` so both stay consistent.
- Add a constant `OPTION_CALENDAR_IDS = 'gcal_tag_filter_calendar_ids'` alongside the existing `OPTION_CALENDAR_ID`.

The migration is read-time only — no upgrade routine needed. Live sites keep rendering on the fallback; the array option is written the first time an admin saves the new multi-select form.

## Fetching & merging

Currently `GCal_Calendar::fetch_events_from_api()` (`includes/class-gcal-calendar.php`) fetches from a single calendar ID with paginated `listEvents`. The AJAX handler `gcal_ajax_fetch_events()` in `gcal-tag-filter.php` duplicates a single-calendar fetch.

### Shared fetch method

Extract the multi-calendar fetch/merge/sort into **one shared method** so both callers use identical logic and cannot drift. Proposed home: a method on `GCal_Calendar`, e.g. `fetch_raw_items_from_calendars( array $calendar_ids, array $params )` that:

1. Loops over each calendar ID.
2. Runs the existing paginated `listEvents` loop (up to the current 10-page / 2500-event-per-page safety cap) **per calendar**.
3. Merges all items into one array.
4. **No de-duplication** — every item from every calendar is kept.
5. **Partial-failure resilience:** wrap each calendar's fetch in try/catch. If one calendar throws, log the error (`error_log`) and continue with the remaining calendars. Only return a `WP_Error` if the calendar list is empty or **every** calendar fails.
6. **Re-sort** the merged array by event start time (ascending) after merging — each calendar returns its own items sorted, but the merge interleaves them, so a final `usort` on start `dateTime`/`date` is required.

`fetch_events_from_api()` becomes: resolve calendar IDs via `get_selected_calendar_ids()`, build the time-range `$params` as today, call the shared method, then run the existing `process_events()` on the merged+sorted items.

`gcal_ajax_fetch_events()` is updated to resolve the calendar IDs and call the same shared method (with its `maxResults => 100` params), then its existing per-event processing loop runs on the merged result.

### Empty-selection guard

Where the code currently checks `! $calendar_id`, check `empty( $calendar_ids )` instead and return the existing "No calendar selected" `WP_Error` / rendered error. Applies to:
- `GCal_Calendar::fetch_events_from_api()`
- `gcal_ajax_fetch_events()` in `gcal-tag-filter.php`
- `GCal_Shortcode` guard at `includes/class-gcal-shortcode.php:112`

## Caching

`GCal_Cache::generate_key()` (`includes/class-gcal-cache.php`) currently includes the single `calendar_id` in the key. Change it to include the **full sorted list** of selected calendar IDs (e.g. `implode( ',', $sorted_ids )`). This makes the cache key change whenever the calendar selection changes, so stale cross-selection caches are never served.

## Admin UI

In `admin/class-gcal-admin.php`:

- **Selection form** (currently the single `<select id="calendar_id">`): replace with a **checkbox list**, one checkbox per available calendar from `get_calendar_list()`, using `name="gcal_tag_filter_calendar_ids[]"` and `value="<calendar id>"`. Show the calendar summary and the `(Primary)` badge as today. Pre-check calendars that are in the current selection.
- **Settings registration** (`register_settings()`): register `gcal_tag_filter_calendar_ids` with a `sanitize_callback` that accepts an array, maps each element through `sanitize_text_field`, and filters out empties. Returns a clean array. (The existing single-option registration can remain for backward compatibility or be removed once the form no longer posts it — remove it, since the form field is being replaced.)
- **Selected-calendar summary** (the "Selected Calendar: X" line): update to list all currently selected calendar names (join with commas), or show the not-configured state when the list is empty.

## Uninstall

`uninstall.php`: add `gcal_tag_filter_calendar_ids` to the list of options deleted on uninstall (keep deleting the legacy `gcal_tag_filter_calendar_id` too).

## Connection test

`GCal_Calendar` connection-test path uses `get_selected_calendar_id()` (first calendar). Leaving it testing the first selected calendar is acceptable — it verifies auth + basic reachability. No change required beyond it resolving through the new accessor.

## Files touched

- `includes/class-gcal-oauth.php` — new option constant + accessors + read-time migration.
- `includes/class-gcal-calendar.php` — shared multi-calendar fetch method, merge, sort, partial-failure handling; empty-selection guard.
- `includes/class-gcal-cache.php` — cache key includes all calendar IDs.
- `includes/class-gcal-shortcode.php` — empty-selection guard.
- `gcal-tag-filter.php` — AJAX handler uses shared fetch; empty-selection guard.
- `admin/class-gcal-admin.php` — checkbox list UI, array option registration + sanitize, summary line.
- `uninstall.php` — delete new option.

## Testing / verification

- Fresh install with two calendars selected → events from both appear, merged and correctly ordered by start time.
- Existing site upgraded (only legacy single option set, new array empty) → still renders that one calendar with no admin action (migration fallback).
- Save the new checkbox form with 0, 1, and 2+ calendars → option stores the expected array; 0 selected shows the "No calendar selected" message.
- Same event on two selected calendars → appears twice (show-all confirmed).
- One invalid/inaccessible calendar ID among several → others still render; error logged; no fatal.
- Cache: switching the selection produces different cached output (no stale cross-selection bleed).
