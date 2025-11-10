# Google Calendar Tag Filter Plugin

A WordPress plugin that embeds Google Calendar events with tag-based filtering capabilities using OAuth 2.0 authentication.

## Features

- **OAuth 2.0 Authentication**: Secure read-only access to Google Calendar
- **Tag-Based Filtering**: Filter events using customizable category tags
- **Multiple Views**: Calendar view (year/month/week) and list view with display toggle
- **Dynamic Navigation**: AJAX-based navigation between periods without page reloads
- **Category Sidebar**: Optional category filter sidebar with display style toggle
- **Category Whitelist**: Admin-managed categories with custom colors
- **Smart Caching**: Configurable 1-minute caching for optimal performance
- **WCAG Contrast**: Automatic text color adjustment for accessibility
- **Mobile-First**: Responsive design that works on all devices
- **Timezone Support**: Automatic timezone conversion for visitors
- **Localization Ready**: Fully translatable (French) with text domain support

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Composer**: For installing PHP dependencies
- **Google Cloud Console**: OAuth 2.0 credentials with Calendar API enabled
- **HTTPS**: Required for OAuth redirect (production)

## Installation

### 1. Install Dependencies

```bash
cd /path/to/wp-content/plugins/google-calendar-tag-filter
composer install
```

This will install the Google API PHP Client library.

### 2. Activate the Plugin

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins** → **Installed Plugins**
3. Find "Google Calendar Tag Filter" and click **Activate**

### 3. Configure Google Cloud Console

Before the plugin can connect to Google Calendar, you need to set up OAuth credentials:

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project (or select an existing one)
3. Enable the **Google Calendar API**:
   - Navigate to **APIs & Services** → **Library**
   - Search for "Google Calendar API"
   - Click **Enable**

4. Create OAuth 2.0 credentials:
   - Navigate to **APIs & Services** → **Credentials**
   - Click **Create Credentials** → **OAuth 2.0 Client ID**
   - Select **Web application**
   - Add your redirect URI (found in plugin settings):
     ```
     https://your-site.com/wp-admin/admin.php?page=google-calendar-tag-filter-settings&gcal_oauth_callback=1
     ```
   - Copy the **Client ID** and **Client Secret**

### 4. Connect to Google Calendar

1. Navigate to **Settings** → **Calendar Filter** in WordPress admin
2. Enter your **Client ID** and **Client Secret**
3. Click **Save and Connect with Google**
4. You'll be redirected to Google to authorize access
5. After authorization, select which calendar to display
6. Save your settings

### 5. Configure Categories

1. In the plugin settings, add categories to the whitelist:
   - **Category ID**: Uppercase alphanumeric (e.g., `COMMUNITY`)
   - **Display Name**: User-friendly name (e.g., "Community Events")
   - **Color**: Choose a color for calendar color-coding

2. Example categories:
   - `COMMUNITY` → "Community Events" (Blue)
   - `WORKSHOP` → "Workshops" (Green)
   - `TRAINING` → "Training Sessions" (Yellow)

## Usage

### Tagging Events in Google Calendar

To tag events for filtering, add tags to the event **description** using this format:

```
[[[CATEGORY_ID]]]
```

**Examples:**

- Single tag:
  ```
  [[[COMMUNITY]]]
  Join us for our monthly community meetup!
  ```

- Multiple tags:
  ```
  [[[WORKSHOP]]][[[TRAINING]]]
  Learn React.js in this hands-on workshop.
  ```

- Tags with location:
  ```
  [[[COMMUNITY]]]
  Community potluck dinner - bring your favorite dish!

  Location: 123 Main St
  ```

**Note:** Tags are stripped from the description before displaying to users.

### Embedding Calendars with Shortcodes

Use the `[gcal_embed]` shortcode to display events on any page or post.

**Parameters:**

- `view` (optional): `calendar` or `list` (default: `list`)
- `period` (optional): `week`, `month`, `year`, or `future` (default: `year`)
- `tags` (optional): Comma-separated category IDs (wildcards supported with `*`)
- `show_categories` (optional): `true` or `false` - Show category filter sidebar (default: `false`)
- `show_display_style` (optional): `true` or `false` - Show calendar/list toggle (default: `false`)
- `hide_past` (optional): `true` or `false` - Hide past events in list view (default: `false`)

**Examples:**

```wordpress
<!-- Show all events for the current year in calendar view -->
[gcal_embed view="calendar" period="year"]

<!-- Show current month with category sidebar -->
[gcal_embed view="calendar" period="month" show_categories="true"]

<!-- Show upcoming workshops in list view -->
[gcal_embed tags="WORKSHOP" view="list" period="year"]

<!-- Show this week's community events in calendar view with both sidebars -->
[gcal_embed tags="COMMUNITY" view="calendar" period="week" show_categories="true" show_display_style="true"]

<!-- Show events with display style toggle (calendar/list) -->
[gcal_embed view="list" period="month" show_display_style="true"]

<!-- Show all upcoming events (future period) -->
[gcal_embed view="list" period="future"]

<!-- Show events matching wildcard pattern -->
[gcal_embed view="list" tags="WORKSHOP*" period="future"]

<!-- Show current year's upcoming events only -->
[gcal_embed view="list" period="year" hide_past="true"]

<!-- Filter multiple categories -->
[gcal_embed tags="COMMUNITY,TRAINING" view="list" period="month"]
```

**New Features:**

- **Future Period (`period="future"`)**: Shows all upcoming events from today through the next 3 years (up to 100 events). Period navigation is hidden for this view.
- **Wildcard Tags (`tags="WORKSHOP*"`)**: Use asterisk (*) to match tag patterns. Examples:
  - `WORKSHOP*` matches all tags starting with WORKSHOP
  - `*-TRAINING` matches all tags ending with -TRAINING
  - `WORKSHOP*,COMMUNITY*` matches multiple patterns (OR logic)
- **Hide Past Events (`hide_past="true"`)**: Filters out past events in list view, useful with year/month/week periods.

**URL Parameters:**

Users can change the view dynamically using URL parameters:
- `?gcal_view=week` - Switch to week view
- `?gcal_view=month` - Switch to month view
- `?gcal_view=year` - Switch to year view
- `?gcal_display=calendar` - Switch to calendar display
- `?gcal_display=list` - Switch to list display
- `?gcal_category=WORKSHOP` - Filter by category

## Cache Management

The plugin caches events to minimize API calls and improve performance:

- **Default**: 1-minute cache
- **Configurable**: 0-60 minutes
- **Manual Clear**: Use the "Clear Cache Now" button in settings

## Troubleshooting

### OAuth Connection Issues

- **Redirect URI mismatch**: Ensure the redirect URI in Google Cloud Console exactly matches the one shown in plugin settings
- **HTTPS required**: OAuth requires HTTPS in production (HTTP is OK for localhost)
- **Token expired**: Click "Disconnect" and reconnect to refresh OAuth tokens

### No Events Displaying

- **Check calendar selection**: Ensure a calendar is selected in settings
- **Check tags**: Verify events have correctly formatted tags in their descriptions
- **Check whitelist**: Ensure the tags used match categories in the whitelist
- **Clear cache**: Try clearing the cache to fetch fresh data

### API Rate Limits

- **Increase cache duration**: Set cache to 5-10 minutes if hitting rate limits
- **Monitor quota**: Check API usage in Google Cloud Console

## Development

### File Structure

```
google-calendar-tag-filter/
├── google-calendar-tag-filter.php          # Main plugin file
├── composer.json                 # Dependencies
├── includes/                     # Core classes
│   ├── class-gcal-oauth.php     # OAuth authentication
│   ├── class-gcal-calendar.php  # Calendar API service
│   ├── class-gcal-parser.php    # Tag parsing
│   ├── class-gcal-cache.php     # Cache management
│   ├── class-gcal-categories.php # Category whitelist
│   └── class-gcal-shortcode.php  # Shortcode handler
├── admin/                        # Admin interface
│   ├── class-gcal-admin.php
│   ├── partials/
│   ├── css/
│   └── js/
├── public/                       # Frontend display
│   ├── class-gcal-display.php
│   ├── css/
│   └── js/
└── languages/                    # Translations
```

### Localization

The plugin is translation-ready with text domain `google-calendar-tag-filter`. To translate:

1. Generate POT file: `wp i18n make-pot . languages/google-calendar-tag-filter.pot`
2. Create translation files (.po/.mo) for your language
3. Place in the `languages/` directory

## Security

- OAuth tokens are encrypted before storage
- All input is sanitized and validated
- Output is properly escaped to prevent XSS
- Nonce verification for all admin actions
- Capability checks for administrative functions

## Support

For issues, feature requests, or contributions:
- **GitHub**: https://github.com/infuseproduct/google-calendar-tag-filter
- **Documentation**: See `/docs` folder

## License

GPL v2 or later

## Credits

Developed by infuseproduct
