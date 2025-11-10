# Google Calendar Tag Filter - User Manual

**Version:** 1.0.1
**Last Updated:** November 2025

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Administrator Guide](#administrator-guide)
4. [Content Editor Guide](#content-editor-guide)
5. [Calendar Manager Guide](#calendar-manager-guide)
6. [Troubleshooting](#troubleshooting)
7. [FAQ](#faq)

---

## Introduction

The Google Calendar Tag Filter plugin allows you to display Google Calendar events on your WordPress site with powerful filtering capabilities. Events can be tagged with categories and displayed in different formats (calendar or list view) across multiple pages.

### Key Features

- 📅 **Multiple Views:** Calendar (year/month/week) and list views with toggle switching
- 🏷️ **Tag-Based Filtering:** Filter events by custom categories with interactive sidebar
- 📱 **Mobile-First:** Responsive design that works on all devices
- 🌍 **Timezone Smart:** Automatically displays times in visitor's timezone (French localization)
- ⚡ **Fast:** Smart caching with AJAX-based navigation for instant period switching
- 🎨 **Customizable:** Color-coded categories with custom display names
- 🔄 **Interactive Filtering:** Real-time category filtering without page reload
- 📆 **Smart Date Ranges:** Shows past and future events within displayed period
- ♿ **WCAG Accessible:** Automatic contrast adjustment for better readability

---

## Getting Started

### What You'll Need

1. **WordPress site** (version 5.8 or higher)
2. **Google account** with calendar access
3. **Google Cloud Console** access (free)
4. **HTTPS** enabled on your site (required for OAuth)

### Quick Setup (5 Minutes)

1. Install and activate the plugin
2. Set up Google Cloud Console credentials ([Setup Guide](SETUP-google-cloud-console.md))
3. Connect your Google Calendar
4. Add category tags
5. Start using shortcodes

---

## Administrator Guide

As an administrator, you'll set up the plugin so content editors can easily embed calendars.

### Initial Setup

#### Step 1: Access Plugin Settings

1. Log in to WordPress admin
2. Navigate to **Settings** → **Calendar Filter**

#### Step 2: Connect to Google Calendar

1. **Get OAuth Credentials:**
   - Follow the [Google Cloud Console Setup Guide](SETUP-google-cloud-console.md)
   - You'll need:
     - Client ID
     - Client Secret

2. **Enter Credentials:**
   - Paste your **Client ID**
   - Paste your **Client Secret**
   - Note the **Redirect URI** shown (you'll need this in Google Cloud Console)

3. **Connect:**
   - Click **"Save and Connect with Google"**
   - Log in to your Google account
   - Authorize the plugin (read-only calendar access)
   - You'll be redirected back to WordPress

4. **Select Calendar:**
   - Choose which calendar to display from the dropdown
   - Click **"Save Calendar Selection"**

#### Step 3: Set Up Categories

Categories are tags you'll use to filter events. Think about how you want to organize your events.

**Example Categories:**
- `COMMUNITY` → "Community Events"
- `WORKSHOP` → "Workshops & Training"
- `MEETING` → "Board Meetings"
- `FUNDRAISER` → "Fundraising Events"
- `VOLUNTEER` → "Volunteer Opportunities"

**To Add a Category:**

1. Scroll to **"Category Whitelist"** section
2. Enter **Category ID:**
   - Must be UPPERCASE
   - Only letters, numbers, underscores, hyphens
   - Example: `COMMUNITY`
3. Enter **Display Name:**
   - User-friendly name shown to visitors
   - Example: "Community Events"
4. Choose a **Color:**
   - Used for color-coding in calendar view
   - Click the color picker to choose
5. Click **"Add Category"**

**To Edit a Category:**

1. Find the category in the list
2. Click **"Edit"**
3. Update the display name or color
4. Click **"Save Changes"**

**To Delete a Category:**

1. Click **"Delete"** next to the category
2. Confirm the deletion
3. **Note:** You cannot delete categories that are currently in use

#### Step 4: Configure Cache Settings

Caching improves performance by storing calendar data temporarily.

1. **Set Cache Duration:**
   - Use the slider: 0-60 minutes
   - **Recommended:** 1-5 minutes for active calendars
   - **0 minutes:** No caching (always fresh, but slower)

2. **Clear Cache Manually:**
   - Click **"Clear Cache Now"** to force a refresh
   - Useful after making changes in Google Calendar

#### Step 5: Test Connection

1. Click **"Test Connection"** button
2. Verify you see:
   - ✅ Calendar name
   - ✅ Number of events found
3. If test fails, see [Troubleshooting](#troubleshooting)

### Managing the Plugin

#### Reconnecting Google Calendar

If your connection expires or you need to change calendars:

1. Click **"Disconnect"**
2. Confirm disconnection
3. Follow Step 2 again to reconnect

#### Monitoring Cache

View cache statistics:
- **Cached Items:** Number of cached event sets
- **Last Refresh:** When cache was last updated
- **Current Duration:** Active cache setting

---

## Content Editor Guide

As a content editor, you'll embed calendar views on pages and posts using shortcodes.

### Understanding Shortcodes

Shortcodes are simple text codes that display the calendar:

```
[gcal_embed view="calendar" period="month"]
```

### Shortcode Parameters

Every shortcode needs these parameters:

| Parameter | Required | Options | Description |
|-----------|----------|---------|-------------|
| `view` | ❌ No | `calendar` or `list` (default: `list`) | How to display events |
| `period` | ❌ No | `week`, `month`, `year`, or `future` (default: `year`) | Time range to display |
| `tags` | ❌ No | Category IDs (comma-separated, wildcards supported) | Filter by categories |
| `show_categories` | ❌ No | `true` or `false` (default: `false`) | Show category filter sidebar |
| `show_display_style` | ❌ No | `true` or `false` (default: `false`) | Show view toggle (calendar/list) |
| `hide_past` | ❌ No | `true` or `false` (default: `false`) | Hide past events (list view only) |

### Common Use Cases

#### Show All Events This Month (Calendar View)

```
[gcal_embed view="calendar" period="month"]
```

**Result:** Full calendar grid showing all events for the current month

---

#### Show All Events This Year (List View)

```
[gcal_embed tags="COMMUNITY" view="list" period="year"]
```

**Result:** List of all community events for the current year (January-December)

---

#### Show This Week's Workshops (Calendar View)

```
[gcal_embed tags="WORKSHOP" view="calendar" period="week"]
```

**Result:** 7-day calendar showing only workshop events

---

#### Show Multiple Categories (List View)

```
[gcal_embed tags="WORKSHOP,TRAINING,MEETING" view="list" period="month"]
```

**Result:** List of workshops, training, or meetings this month

---

#### Show Calendar with Category Sidebar

```
[gcal_embed view="calendar" period="month" show_categories="true"]
```

**Result:** Calendar with sidebar showing all categories. Visitors can click categories to filter events in real-time.

---

#### Show Calendar with View Toggle and Categories

```
[gcal_embed view="calendar" period="month" show_categories="true" show_display_style="true"]
```

**Result:** Calendar with sidebar containing:
- View toggle buttons (switch between calendar and list)
- Category filter buttons

This is the most interactive display option, perfect for main calendar pages.

---

#### Show Year View with All Months

```
[gcal_embed view="calendar" period="year"]
```

**Result:** Displays a 12-month grid showing all events for the current year. Each month shows:
- Month name and event count
- First 5 events with date and title
- "See more" button if more than 5 events
- Color-coded dots for categories

**Navigation:** Visitors can use prev/next arrows to navigate to different years (2024, 2025, 2026, etc.)

---

#### Pre-filter with Interactive Categories

```
[gcal_embed tags="WORKSHOP" view="calendar" period="week" show_categories="true"]
```

**Result:** Calendar pre-filtered to workshops, but visitors can switch to other categories using the sidebar.

---

#### Show All Upcoming Events (Future Period)

```
[gcal_embed view="list" tags="MESSE-MUI-WO" period="future"]
```

**Result:** List of all upcoming events for the MESSE-MUI-WO tag, spanning from today through the next 3 years (up to 100 events from Google Calendar API limit). **No period navigation controls are shown** since this view shows all future events.

---

#### Show All Future Events with Past Events Hidden

```
[gcal_embed view="list" tags="COMMUNITY" period="future" hide_past="true"]
```

**Result:** Same as above with explicit past event filtering. The `hide_past` parameter is particularly useful when combined with regular periods (year, month, week) to exclude events that have already occurred.

---

#### Show Events Matching Tag Pattern (Wildcard)

```
[gcal_embed view="list" tags="MESSE*" period="future"]
```

**Result:** Shows all upcoming events with tags starting with "MESSE" (e.g., MESSE-MUI-WO, MESSE-AUTRE, MESSE-SPECIAL). The asterisk (*) acts as a wildcard matching zero or more characters.

**Additional wildcard examples:**

- `tags="MESSE*"` - Matches all tags starting with MESSE
- `tags="*-WO"` - Matches all tags ending with -WO
- `tags="MESSE*,REUNION*"` - Matches tags starting with MESSE or REUNION (OR logic)

**Wildcard Rules:**

- Only alphanumeric characters, hyphens, underscores, and asterisk allowed
- Wildcards bypass the category whitelist (no need to pre-define them)
- Case-insensitive matching (automatically converted to uppercase)

---

#### Show Current Year with Hidden Past Events

```
[gcal_embed view="list" period="year" hide_past="true"]
```

**Result:** Shows only upcoming events from today through December 31 of the current year. Past events are filtered out.

---

### How to Add a Shortcode

#### In Classic Editor:

1. Edit your page or post
2. Place cursor where you want the calendar
3. Type (or paste) the shortcode
4. Click **"Update"** or **"Publish"**

#### In Block Editor (Gutenberg):

1. Edit your page or post
2. Click **+** to add a block
3. Search for **"Shortcode"** block
4. Type (or paste) the shortcode
5. Click **"Update"** or **"Publish"**

#### In Page Builders:

Most page builders have a "Shortcode" or "HTML" widget. Add the shortcode there.

### Calendar Navigation

The calendar includes interactive navigation features:

**Period Navigation:**
- **Prev/Next Arrows:** Navigate between periods (previous/next week, month, or year)
- **AJAX Loading:** Navigation happens instantly without page reload
- **View Toggle Buttons:** Switch between Week, Month, and Year views

**URL Parameters:**

Visitors can bookmark specific views using URL parameters:
- `?gcal_view=week` - Show week view
- `?gcal_view=month` - Show month view
- `?gcal_view=year` - Show year view
- `?gcal_display=calendar` - Show calendar display
- `?gcal_display=list` - Show list display
- `?gcal_category=WORKSHOP` - Filter by specific category

**Example URLs:**
```
https://yoursite.com/calendar/?gcal_view=year
https://yoursite.com/calendar/?gcal_view=month&gcal_category=COMMUNITY
https://yoursite.com/calendar/?gcal_display=list&gcal_view=week
```

### Tips for Content Editors

✅ **DO:**
- Use descriptive tags to filter events
- Mix calendar and list views on different pages
- Test the shortcode after adding it
- Use year view for annual overviews
- Enable `show_display_style` for flexible viewing options

❌ **DON'T:**
- Use tags that aren't in the whitelist (they'll be ignored)
- Use lowercase in tag names (always UPPERCASE)
- Overload a single page with too many calendar instances

---

## Calendar Manager Guide

As a calendar manager, you'll tag events in Google Calendar so they appear in filtered views.

### Tagging Events

Events are tagged by adding special codes to the event **description**.

#### Tag Format

```
[[[CATEGORY_ID]]]
```

**Example:**
```
[[[COMMUNITY]]]
```

**Note:** The simplified format no longer requires "TAG:" prefix.

### Single Tag Example

**Event:** Community Potluck Dinner

**How to Tag:**

1. Open the event in Google Calendar
2. Click **Edit** (pencil icon)
3. In the **Description** field, add:
   ```
   [[[COMMUNITY]]]

   Join us for our monthly potluck dinner! Bring your favorite dish to share.

   What to bring:
   - Main dish, side, or dessert
   - Your own plate and utensils
   ```
4. Click **Save**

**Result:** This event will appear when filtering by `COMMUNITY` tag.

---

### Multiple Tags Example

**Event:** Volunteer Training Workshop

**How to Tag:**

1. Edit the event
2. Add multiple tags to the description:
   ```
   [[[VOLUNTEER]]][[[WORKSHOP]]][[[TRAINING]]]

   Learn how to become a volunteer at our organization. This workshop covers:
   - Volunteer roles and responsibilities
   - Safety procedures
   - Q&A with current volunteers

   Registration required.
   ```
3. Save the event

**Result:** This event appears when filtering by `VOLUNTEER`, `WORKSHOP`, or `TRAINING`.

---

### Tagging Best Practices

#### ✅ DO:

- **Add tags at the very beginning** of the description
- **Use multiple tags** if an event fits multiple categories
- **Check the whitelist** - only use approved category IDs
- **Keep tags together** - don't spread them throughout the description
- **Write description text after tags** - users won't see the tag codes

#### ❌ DON'T:

- Don't use lowercase: `[[[community]]]` ❌ (won't work)
- Don't add spaces: `[[[COMMUNITY ]]]` ❌ (won't work)
- Don't misspell: `[[[COMUNITY]]]` ❌ (won't be recognized)
- Don't use non-whitelisted tags (they'll be ignored)

### What Happens to Tags

**Important:** Tags are **invisible to website visitors**. The plugin:
1. ✅ Reads tags from descriptions
2. ✅ Uses them to filter events
3. ✅ **Removes tags** before displaying descriptions
4. ✅ Shows only your actual event description

### Tag Examples by Category

#### Community Events
```
[[[COMMUNITY]]]

Monthly neighborhood meetup. Light refreshments provided.
```

#### Workshops
```
[[[WORKSHOP]]]

Hands-on coding workshop. Bring your laptop. All skill levels welcome.
```

#### Board Meetings
```
[[[MEETING]]]

Monthly board meeting. Members only. Agenda will be sent via email.
```

#### Fundraisers
```
[[[FUNDRAISER]]][[[COMMUNITY]]]

Annual gala dinner to support our programs. Tickets: $50/person
```

### Checking Your Tags

After tagging events:

1. View the calendar on your website
2. Use different tag filters in shortcodes
3. Verify events appear in the correct filtered views
4. Check that tag codes don't appear in event descriptions

---

## Troubleshooting

### Events Not Showing Up

**Problem:** Calendar or list is empty

**Possible Causes:**

1. **No events match the filter**
   - Check if events have the correct tags
   - Try viewing without tags: `[gcal_embed view="list" period="month"]`

2. **Tags aren't recognized**
   - Verify tag format: `[[[CATEGORYID]]]`
   - Check category is in whitelist (WordPress admin)
   - Ensure UPPERCASE
   - Don't use old format with "TAG:" prefix

3. **Cache is stale**
   - Clear cache in plugin settings
   - Wait for cache to expire (default: 1 minute)

4. **Calendar not connected**
   - Check connection in Settings → Calendar Filter
   - Test connection
   - Reconnect if needed

### Tags Visible to Users

**Problem:** Tags like `[[[COMMUNITY]]]` appear in event descriptions

**Cause:** Tag format is incorrect

**Solution:**
- Must be exactly: `[[[CATEGORYID]]]`
- Three opening brackets, three closing brackets
- All UPPERCASE
- No spaces
- No "TAG:" prefix (simplified format)

### Connection Errors

**Problem:** "Not authenticated" or "Connection failed"

**Solutions:**

1. **OAuth expired:**
   - Go to Settings → Calendar Filter
   - Click "Disconnect"
   - Reconnect following setup steps

2. **Wrong credentials:**
   - Verify Client ID and Secret in Google Cloud Console
   - Re-enter in WordPress
   - Ensure no extra spaces

3. **Redirect URI mismatch:**
   - Check redirect URI in WordPress matches Google Cloud Console exactly
   - See [Google Cloud Setup Guide](SETUP-google-cloud-console.md)

### Timezone Issues

**Problem:** Event times showing in wrong timezone

**This is normal!** The plugin displays times in each visitor's browser timezone automatically, formatted in French (24-hour format).

- Event stored in Google Calendar: 2:00 PM PST (14:00)
- Visitor in EST sees: 17:00 (5:00 PM EST)
- Visitor in PST sees: 14:00 (2:00 PM PST)
- Dates show in French: "9 novembre 2025 à 17:00"

### Performance Issues

**Problem:** Calendar loads slowly

**Solutions:**

1. **Increase cache duration:**
   - Go to Settings → Calendar Filter
   - Set cache to 5-10 minutes

2. **Limit events:**
   - Use specific periods (`week` or `month` instead of `year`)
   - Use tag filters to reduce event count

3. **Check API quota:**
   - Log in to Google Cloud Console
   - Check Calendar API usage
   - Ensure you haven't hit rate limits

---

## FAQ

### General Questions

**Q: How many calendars can I connect?**
A: Currently one calendar per site. You can switch calendars anytime in settings.

**Q: Can I display multiple calendars?**
A: Not in v1.0. This is planned for a future update.

**Q: Is this plugin free?**
A: Yes! The plugin is free. Google Calendar API is also free for normal usage.

**Q: Does this work with Google Workspace?**
A: Yes! Works with both personal Google accounts and Workspace accounts.

### Privacy & Security

**Q: What permissions does the plugin request?**
A: Read-only access to your Google Calendar. It cannot modify, create, or delete events.

**Q: Is my data secure?**
A: Yes. OAuth tokens are encrypted before storage. The plugin follows WordPress security best practices.

**Q: Can visitors see my full calendar?**
A: Only events you choose to display. Use tags to control what's visible on each page.

**Q: Does the plugin track visitors?**
A: No. The plugin only displays calendar data. No visitor tracking.

### Customization

**Q: Can I change the calendar colors?**
A: Yes! Each category has a color setting in the admin panel.

**Q: Can I customize the layout?**
A: The plugin provides default responsive layouts. Advanced users can add custom CSS to their theme.

**Q: Can I translate the plugin?**
A: Yes! The plugin is translation-ready. Use the text domain `google-calendar-tag-filter`.

**Q: Can I change date/time formats?**
A: The plugin formats dates in French (fr-FR) with 24-hour time format. Times are automatically adjusted to each visitor's timezone.

### Technical Questions

**Q: What PHP version do I need?**
A: PHP 7.4 or higher

**Q: What WordPress version do I need?**
A: WordPress 5.8 or higher

**Q: Does it work with page builders?**
A: Yes! Works with Gutenberg, Classic Editor, Elementor, Beaver Builder, and most page builders.

**Q: Does it work with caching plugins?**
A: Yes! The plugin has its own internal cache and is compatible with WP Super Cache, W3 Total Cache, etc.

**Q: Can I use multiple shortcodes on one page?**
A: Yes! You can display multiple filtered views on the same page.

### Limits

**Q: How many events can I display?**
A: Up to 100 events per period. Calendar views show all events for the selected period (week, month, or year).

**Q: How many categories can I create?**
A: Unlimited! Create as many categories as you need.

**Q: Are there API rate limits?**
A: Google Calendar API allows 1 million requests per day. Normal usage won't hit this limit thanks to caching.

---

## Getting Help

### Documentation

- **Setup Guide:** [Google Cloud Console Setup](SETUP-google-cloud-console.md)
- **README:** [Plugin README](../README.md)
- **PRD:** [Product Requirements](PRD-google-calendar-tag-filter.md)

### Support Channels

1. **Plugin Settings:** Built-in help text and tooltips
2. **GitHub Issues:** [Report bugs or request features](https://github.com/infuseproduct/google-calendar-tag-filter/issues)
3. **WordPress Debug:** Enable WP_DEBUG for detailed error messages

### Before Asking for Help

Please provide:
- WordPress version
- PHP version
- Plugin version
- Description of the issue
- Error messages (if any)
- Steps to reproduce

---

**Thank you for using Google Calendar Tag Filter!**

*Last Updated: January 2025*
