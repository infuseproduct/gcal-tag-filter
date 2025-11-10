# Shortcode Reference

Complete reference for the `[gcal_embed]` shortcode.

**Last Updated:** January 2025

---

## Quick Reference

```
[gcal_embed view="list" period="future" tags="MESSE*" hide_past="true" show_categories="true" show_display_style="true"]
```

---

## Parameters

### `view`

**Type:** String
**Required:** No
**Default:** `list`
**Options:** `calendar`, `list`

Controls the display format of events.

- **`calendar`**: Grid-based calendar view with days and events
- **`list`**: Vertical list of events with date badges

**Examples:**

```wordpress
[gcal_embed view="calendar" period="month"]
[gcal_embed view="list" period="year"]
```

---

### `period`

**Type:** String
**Required:** No
**Default:** `year`
**Options:** `week`, `month`, `year`, `future`

Defines the time range for displaying events.

- **`week`**: Shows 7 days (Monday-Sunday) starting from the current week
- **`month`**: Shows all days in the current month
- **`year`**: Shows all months in the current year
- **`future`**: Shows all upcoming events from today through the next 3 years (up to 100 events)

**Special Behavior:**

- **Period Navigation:** When `period="future"`, navigation controls (prev/next arrows, week/month/year toggles) are automatically hidden since the view shows all future events.
- **API Limit:** The `future` period respects Google Calendar API's 100-event limit per request.

**Examples:**

```wordpress
[gcal_embed view="calendar" period="week"]
[gcal_embed view="list" period="month"]
[gcal_embed view="list" period="future"]
```

---

### `tags`

**Type:** String (comma-separated)
**Required:** No
**Default:** Empty (shows all events)
**Supports:** Exact matches, wildcard patterns

Filters events by category tags. Multiple tags use OR logic (event matches ANY tag).

**Exact Matching:**

```wordpress
[gcal_embed tags="COMMUNITY"]
[gcal_embed tags="WORKSHOP,TRAINING,MEETING"]
```

**Wildcard Matching:**

Use asterisk (`*`) to match patterns:

```wordpress
[gcal_embed tags="MESSE*"]                    <!-- Matches: MESSE-MUI-WO, MESSE-AUTRE, etc. -->
[gcal_embed tags="*-WO"]                      <!-- Matches: MESSE-MUI-WO, REUNION-WO, etc. -->
[gcal_embed tags="MESSE*,REUNION*"]          <!-- Matches tags starting with MESSE or REUNION -->
```

**Wildcard Rules:**

- Only alphanumeric characters, hyphens (-), underscores (_), and asterisk (*) allowed
- Wildcards bypass the category whitelist (no need to pre-define patterns)
- Case-insensitive (automatically converted to uppercase)
- Pattern matching uses regex internally

**Tag Format in Google Calendar:**

Events must be tagged in their description:

```
[[[CATEGORY_ID]]]

Your event description here.
```

---

### `show_categories`

**Type:** Boolean
**Required:** No
**Default:** `false`
**Options:** `true`, `false`

Shows/hides the category filter sidebar with interactive filtering buttons.

**When enabled:**

- Displays a sidebar with all configured categories
- Users can click categories to filter events in real-time
- Works with both calendar and list views
- On mobile: Shows as a dropdown instead of buttons

**Examples:**

```wordpress
[gcal_embed view="calendar" period="month" show_categories="true"]
[gcal_embed view="list" period="year" show_categories="true"]
```

---

### `show_display_style`

**Type:** Boolean
**Required:** No
**Default:** `false`
**Options:** `true`, `false`

Shows/hides the display style toggle (calendar/list switch) in the sidebar.

**When enabled:**

- Adds toggle buttons to switch between calendar and list views
- Changes happen instantly without page reload
- Located in the sidebar (requires sidebar to be shown)
- Often used together with `show_categories="true"`

**Examples:**

```wordpress
[gcal_embed view="calendar" period="month" show_display_style="true"]
[gcal_embed view="list" period="year" show_categories="true" show_display_style="true"]
```

---

### `hide_past`

**Type:** Boolean
**Required:** No
**Default:** `false`
**Options:** `true`, `false`

Filters out past events from the display (list view only).

**Behavior:**

- **All-day events:** Compared by date only (event visible until end of day in Hong Kong timezone)
- **Timed events:** Compared by exact timestamp with timezone
- **Only affects list view:** Calendar view shows all events for the period
- **Works with all periods:** Especially useful with `period="year"` to show only upcoming events

**Timezone Handling:**

- Uses Asia/Hong_Kong timezone for date comparisons
- All-day events: end date is exclusive (event on Oct 25 has end = Oct 26)
- Shows event until 23:59:59 of its actual end date

**Examples:**

```wordpress
<!-- Show only upcoming events this year -->
[gcal_embed view="list" period="year" hide_past="true"]

<!-- Show all future events (redundant with period="future" but explicit) -->
[gcal_embed view="list" period="future" hide_past="true"]

<!-- Show upcoming events for specific category -->
[gcal_embed view="list" tags="WORKSHOP" period="month" hide_past="true"]
```

---

## Complete Examples

### Basic Calendar Views

```wordpress
<!-- Simple calendar showing current month -->
[gcal_embed view="calendar" period="month"]

<!-- Simple list showing current year -->
[gcal_embed view="list" period="year"]

<!-- Week view in calendar format -->
[gcal_embed view="calendar" period="week"]
```

### Filtered Views

```wordpress
<!-- Show only community events -->
[gcal_embed tags="COMMUNITY" view="list" period="year"]

<!-- Show workshops and training -->
[gcal_embed tags="WORKSHOP,TRAINING" view="calendar" period="month"]

<!-- Show all events matching pattern -->
[gcal_embed tags="MESSE*" view="list" period="future"]
```

### Interactive Views with Sidebar

```wordpress
<!-- Calendar with category filter -->
[gcal_embed view="calendar" period="month" show_categories="true"]

<!-- List with view toggle -->
[gcal_embed view="list" period="year" show_display_style="true"]

<!-- Full-featured calendar page -->
[gcal_embed view="calendar" period="month" show_categories="true" show_display_style="true"]
```

### Future Events

```wordpress
<!-- All upcoming events -->
[gcal_embed view="list" period="future"]

<!-- Upcoming events for specific tag -->
[gcal_embed view="list" tags="MESSE-MUI-WO" period="future"]

<!-- Upcoming events matching pattern -->
[gcal_embed view="list" tags="MESSE*" period="future"]

<!-- Upcoming events with interactive filters -->
[gcal_embed view="list" period="future" show_categories="true" show_display_style="true"]
```

### Hide Past Events

```wordpress
<!-- Show only upcoming events this year -->
[gcal_embed view="list" period="year" hide_past="true"]

<!-- This month's upcoming events only -->
[gcal_embed view="list" period="month" hide_past="true"]

<!-- Upcoming workshops -->
[gcal_embed view="list" tags="WORKSHOP" period="year" hide_past="true"]
```

### Advanced Combinations

```wordpress
<!-- All upcoming MESSE events with interactive filters -->
[gcal_embed view="list" tags="MESSE*" period="future" show_categories="true" show_display_style="true"]

<!-- This year's upcoming community events -->
[gcal_embed view="list" tags="COMMUNITY" period="year" hide_past="true"]

<!-- Month view with all features enabled -->
[gcal_embed view="calendar" period="month" show_categories="true" show_display_style="true"]

<!-- Pre-filtered with option to change categories -->
[gcal_embed tags="WORKSHOP" view="calendar" period="week" show_categories="true"]
```

---

## URL Parameters

Users can override shortcode parameters using URL parameters for dynamic filtering:

### Period Override

- `?gcal_view=week` - Switch to week view
- `?gcal_view=month` - Switch to month view
- `?gcal_view=year` - Switch to year view

### Display Override

- `?gcal_display=calendar` - Switch to calendar display
- `?gcal_display=list` - Switch to list display

### Category Filter

- `?gcal_category=WORKSHOP` - Filter by specific category
- `?gcal_category=` - Clear category filter (show all)

### Combined Examples

```
https://yoursite.com/calendar/?gcal_view=year
https://yoursite.com/calendar/?gcal_view=month&gcal_category=COMMUNITY
https://yoursite.com/calendar/?gcal_display=list&gcal_view=week
```

**Note:** URL parameters are preserved during navigation and can be bookmarked.

---

## Best Practices

### Performance

1. **Use appropriate periods:**
   - `week` for event details pages
   - `month` for main calendar pages
   - `year` for overview pages
   - `future` for upcoming events lists

2. **Filter with tags:**
   - Reduces API load
   - Improves page load time
   - More focused user experience

3. **Enable caching:**
   - Default 1-minute cache is recommended
   - Increase to 5-10 minutes for high-traffic sites

### User Experience

1. **Main calendar page:**
   ```wordpress
   [gcal_embed view="calendar" period="month" show_categories="true" show_display_style="true"]
   ```

2. **Upcoming events sidebar:**
   ```wordpress
   [gcal_embed view="list" tags="COMMUNITY" period="month" hide_past="true"]
   ```

3. **Category-specific pages:**
   ```wordpress
   [gcal_embed tags="WORKSHOP" view="list" period="future"]
   ```

4. **Annual overview:**
   ```wordpress
   [gcal_embed view="calendar" period="year"]
   ```

### Mobile Optimization

- Category sidebar automatically converts to dropdown on mobile
- List view is more mobile-friendly than calendar view
- Touch-friendly navigation controls
- Responsive design adapts to all screen sizes

---

## Troubleshooting

### No Events Showing

1. **Check tags**: Ensure events have correct tags in Google Calendar
2. **Check whitelist**: Verify tags are in category whitelist (unless using wildcards)
3. **Check period**: Try `period="future"` to see all upcoming events
4. **Clear cache**: Use "Clear Cache Now" in plugin settings

### Period Navigation Not Working

- **With `period="future"`**: Navigation is intentionally hidden
- **AJAX issues**: Check browser console for JavaScript errors
- **Cache**: Clear cache if navigation shows old data

### Wildcard Not Matching

1. **Check pattern**: Use only alphanumeric, `-`, `_`, and `*`
2. **Check case**: Automatically uppercased, but verify format
3. **Test exact match**: Try without wildcard first to verify tags exist

---

## See Also

- [User Manual](USER-MANUAL.md) - Complete user guide
- [Setup Guide](SETUP-google-cloud-console.md) - Google Cloud Console setup
- [README](../README.md) - Plugin overview

---

**Last Updated:** January 2025
