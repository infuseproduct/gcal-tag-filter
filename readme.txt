=== Google Calendar Tag Filter ===
Contributors: infuseproduct
Tags: google calendar, events, calendar, oauth, tag filter
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embeds Google Calendar events with tag-based filtering capabilities using OAuth 2.0 authentication.

== Description ==

Google Calendar Tag Filter is a powerful WordPress plugin that seamlessly embeds your Google Calendar events with advanced tag-based filtering. Perfect for organizations, communities, and businesses that need to display categorized events with a beautiful, responsive interface.

= Key Features =

* **Secure OAuth 2.0 Authentication** - Read-only access to your Google Calendar with industry-standard security
* **Tag-Based Filtering** - Organize events by categories using customizable tags
* **Multiple Display Views** - Calendar view (year/month/week) and list view with easy toggle
* **Dynamic AJAX Navigation** - Browse between periods without page reloads for smooth user experience
* **Category Sidebar** - Optional filterable sidebar with custom category colors
* **Smart Caching** - Configurable caching (1-60 minutes) for optimal performance
* **WCAG Accessibility** - Automatic contrast adjustment for better readability
* **Mobile-First Design** - Fully responsive across all devices
* **Timezone Support** - Automatic timezone conversion for your visitors
* **Translation Ready** - Fully translatable with included text domain
* **Shareable Event Links** - Copy-to-clipboard functionality for easy event sharing
* **Wildcard Tag Matching** - Use patterns like `MESSE*` to match multiple tags

= Perfect For =

* Churches and religious organizations
* Community centers and non-profits
* Schools and educational institutions
* Businesses with public events
* Coworking spaces and meetup groups
* Any organization managing categorized events

= How It Works =

1. Connect your Google Calendar using OAuth 2.0
2. Tag your Google Calendar events with category identifiers in the description (e.g., `[[[WORKSHOP]]]`)
3. Configure your category whitelist with custom colors
4. Embed events on any page using the `[gcal_embed]` shortcode
5. Users can filter events by category, view, and time period

= Shortcode Examples =

Display current year in calendar view:
`[gcal_embed view="calendar" period="year"]`

Show upcoming workshops with category filter:
`[gcal_embed tags="WORKSHOP" view="list" period="year" show_categories="true"]`

Show all future events:
`[gcal_embed view="list" period="future"]`

Use wildcard matching:
`[gcal_embed view="list" tags="WORKSHOP*" period="future"]`

= Privacy & Security =

* OAuth tokens are encrypted before storage
* All input is sanitized and validated
* Output is properly escaped to prevent XSS
* Nonce verification for all admin actions
* Read-only calendar access - the plugin cannot modify your Google Calendar

== Installation ==

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to Plugins → Add New
3. Search for "Google Calendar Tag Filter"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` directory
3. Extract the archive
4. Navigate to wp-content/plugins/google-calendar-tag-filter
5. Run `composer install` to install dependencies
6. Activate the plugin through the WordPress Plugins menu

= Google Cloud Setup =

Before using the plugin, you need to set up OAuth credentials:

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Google Calendar API
4. Create OAuth 2.0 credentials (Web application type)
5. Add your redirect URI (found in plugin settings):
   `https://yoursite.com/wp-admin/admin.php?page=google-calendar-tag-filter-settings&gcal_oauth_callback=1`
6. Copy the Client ID and Client Secret

= Plugin Configuration =

1. Navigate to Settings → Calendar Filter in WordPress
2. Enter your Client ID and Client Secret
3. Click "Save and Connect with Google"
4. Authorize the plugin to access your calendar (read-only)
5. Select which calendar to display
6. Configure your category whitelist with IDs, names, and colors
7. Save your settings

= Usage =

Add tags to your Google Calendar event descriptions:

Single tag:
`[[[COMMUNITY]]]`

Multiple tags:
`[[[WORKSHOP]]][[[TRAINING]]]`

Then embed on any page:
`[gcal_embed view="calendar" period="month" show_categories="true"]`

== Frequently Asked Questions ==

= Do I need a Google Cloud account? =

Yes, you need to create OAuth 2.0 credentials in Google Cloud Console. This ensures secure, authorized access to your calendar. The plugin provides step-by-step instructions.

= Can the plugin modify my Google Calendar? =

No, the plugin requests read-only access to your calendar. It can only display events, not create, edit, or delete them.

= How do I tag events for filtering? =

Add tags to your event descriptions in Google Calendar using the format `[[[CATEGORY_ID]]]`. Tags are automatically stripped from the displayed description.

= What happens if I exceed Google's API quota? =

The plugin includes smart caching to minimize API calls. If you hit rate limits, increase the cache duration in settings (we recommend 5-10 minutes for high-traffic sites).

= Does it work with multiple calendars? =

Currently, the plugin displays events from one selected calendar. You can change which calendar to display in the settings.

= Is HTTPS required? =

HTTPS is required for production sites (OAuth 2.0 requirement). HTTP is allowed for localhost development.

= Can I customize the styling? =

Yes! The plugin uses clean CSS that can be overridden in your theme. All markup uses BEM methodology for easy targeting.

= Does it support recurring events? =

Yes, the plugin fully supports Google Calendar's recurring events and will display all instances within the selected time period.

= What about performance? =

The plugin includes smart caching (default 1 minute, configurable up to 60 minutes) and AJAX navigation to minimize server load and provide a fast user experience.

= Is it translation ready? =

Yes! The plugin is fully internationalized with text domain `google-calendar-tag-filter`. POT files can be generated using WP-CLI.

== Screenshots ==

1. Calendar view with year navigation
2. List view with event details
3. Category filter sidebar
4. Admin settings page - OAuth connection
5. Admin settings page - Category manager
6. Event modal with shareable link
7. Mobile responsive design

== Changelog ==

= 1.0.1 =
* Initial release

== Privacy Policy ==

This plugin connects to Google Calendar API using OAuth 2.0 authentication. The plugin:

* Stores encrypted OAuth tokens in your WordPress database
* Makes API requests to Google Calendar on behalf of authorized users
* Does not collect or transmit any user data to third parties
* Does not track user behavior
* Caches calendar data temporarily (configurable 1-60 minutes)

Users should review Google's Privacy Policy regarding Calendar API usage.

== Developer Notes ==

= Hooks & Filters =

The plugin provides several hooks for customization:

* `gcal_tag_filter_event_output` - Filter event HTML output
* `gcal_tag_filter_cache_key` - Customize cache key generation
* `gcal_tag_filter_categories` - Filter available categories

= Shortcode Parameters =

* `view` - Display mode: "calendar" or "list" (default: "list")
* `period` - Time range: "week", "month", "year", or "future" (default: "year")
* `tags` - Comma-separated category IDs, supports wildcards (e.g., "WORKSHOP" or "MESSE*")
* `show_categories` - Show category filter sidebar: "true" or "false" (default: "false")
* `show_display_style` - Show calendar/list toggle: "true" or "false" (default: "false")
* `hide_past` - Hide past events in list view: "true" or "false" (default: "false")

= File Structure =

```
google-calendar-tag-filter/
├── google-calendar-tag-filter.php          # Main plugin file
├── includes/                     # Core classes
├── admin/                        # Admin interface
├── public/                       # Frontend display
└── languages/                    # Translations
```

Full documentation available on GitHub.
