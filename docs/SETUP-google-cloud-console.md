# Google Cloud Console Setup Guide

This guide walks you through setting up OAuth 2.0 credentials in Google Cloud Console for the Google Calendar Tag Filter plugin.

## Prerequisites

- A Google account (personal or workspace)
- Access to [Google Cloud Console](https://console.cloud.google.com/)
- WordPress site with HTTPS enabled (required for OAuth in production)

## Estimated Time

15-20 minutes for first-time setup

---

## Step 1: Create or Select a Google Cloud Project

### 1.1 Go to Google Cloud Console

Navigate to [console.cloud.google.com](https://console.cloud.google.com/)

### 1.2 Create a New Project (or Select Existing)

**Option A: Create New Project**

1. Click the project dropdown at the top of the page (next to "Google Cloud")
2. Click **"NEW PROJECT"**
3. Enter project details:
   - **Project name**: `WordPress Calendar Plugin` (or your preferred name)
   - **Organization**: Select your organization (if applicable)
   - **Location**: Leave as default or select your organization
4. Click **"CREATE"**
5. Wait for the project to be created (takes ~30 seconds)
6. Ensure your new project is selected in the dropdown

**Option B: Use Existing Project**

1. Click the project dropdown
2. Select your existing project from the list

---

## Step 2: Enable Google Calendar API

### 2.1 Navigate to API Library

1. In the left sidebar, click **"APIs & Services"** → **"Library"**
   - Or use the search bar at the top: type "API Library"

### 2.2 Search for Calendar API

1. In the API Library search box, type: `Google Calendar API`
2. Click on **"Google Calendar API"** in the results

### 2.3 Enable the API

1. Click the blue **"ENABLE"** button
2. Wait for confirmation (takes a few seconds)
3. You should see "API enabled" confirmation

---

## Step 3: Configure OAuth Consent Screen

Before creating credentials, you must configure the OAuth consent screen.

### 3.1 Navigate to OAuth Consent Screen

1. In the left sidebar, click **"APIs & Services"** → **"OAuth consent screen"**

### 3.2 Choose User Type

**For Personal/Small Sites:**
- Select **"External"**
- Click **"CREATE"**

**For Google Workspace Organizations:**
- Select **"Internal"** (only if you want to restrict to your organization)
- Click **"CREATE"**

### 3.3 Fill Out App Information

**OAuth consent screen (Page 1/4):**

1. **App information:**
   - **App name**: `WordPress Calendar` (or your site name)
   - **User support email**: Your email address
   - **App logo**: (Optional) Upload your site logo

2. **App domain (Optional):**
   - **Application home page**: `https://yoursite.com`
   - **Privacy policy link**: `https://yoursite.com/privacy` (if you have one)
   - **Terms of service**: `https://yoursite.com/terms` (if you have one)

3. **Authorized domains:**
   - Click **"+ ADD DOMAIN"**
   - Enter your domain: `yoursite.com` (without https://)
   - Press Enter

4. **Developer contact information:**
   - **Email addresses**: Your email address

5. Click **"SAVE AND CONTINUE"**

### 3.4 Configure Scopes

**Scopes (Page 2/4):**

1. Click **"ADD OR REMOVE SCOPES"**
2. In the filter box, search for: `calendar.readonly`
3. Check the box for:
   - `../auth/calendar.readonly` - "See and download any calendar you can access using your Google Calendar"
4. Click **"UPDATE"**
5. Verify the scope appears in the list
6. Click **"SAVE AND CONTINUE"**

### 3.5 Add Test Users (External User Type Only)

**Test users (Page 3/4):**

> **Note:** If you selected "External" user type, your app will be in "Testing" mode and limited to 100 users. You need to add test users.

1. Click **"+ ADD USERS"**
2. Enter email addresses of users who will test the app (including yourself)
3. Click **"ADD"**
4. Click **"SAVE AND CONTINUE"**

**To remove the 100 user limit (optional):**
- You can publish your app later by going back to OAuth consent screen and clicking "PUBLISH APP"
- This requires verification if you use sensitive scopes

### 3.6 Review Summary

**Summary (Page 4/4):**

1. Review all information
2. Click **"BACK TO DASHBOARD"**

---

## Step 4: Create OAuth 2.0 Credentials

### 4.1 Navigate to Credentials

1. In the left sidebar, click **"APIs & Services"** → **"Credentials"**

### 4.2 Create OAuth Client ID

1. Click **"+ CREATE CREDENTIALS"** at the top
2. Select **"OAuth client ID"**

### 4.3 Configure OAuth Client

1. **Application type**: Select **"Web application"**

2. **Name**: Enter a name for this credential
   - Example: `WordPress Calendar Plugin - Production`

3. **Authorized JavaScript origins** (Optional):
   - Click **"+ ADD URI"**
   - Enter: `https://yoursite.com`
   - You can add multiple domains if needed

4. **Authorized redirect URIs** (IMPORTANT):
   - Click **"+ ADD URI"**
   - Enter your WordPress redirect URI (found in plugin settings):
     ```
     https://yoursite.com/wp-admin/admin.php?page=google-calendar-tag-filter-settings&gcal_oauth_callback=1
     ```
   - **Replace `yoursite.com` with your actual domain**
   - **Must include `https://` and the exact path**
   - **Must match exactly** - even a trailing slash difference will cause errors

5. Click **"CREATE"**

### 4.4 Copy Your Credentials

A popup will appear with your credentials:

1. **Client ID**: Looks like `123456789-abcdefghijk.apps.googleusercontent.com`
   - Click the copy icon to copy it
   - Save it somewhere temporarily (you'll need it in WordPress)

2. **Client Secret**: Looks like `GOCSPX-abcd1234efgh5678`
   - Click the copy icon to copy it
   - Save it somewhere temporarily

3. Click **"OK"** to close the popup

> **⚠️ Security Note:** Keep your Client Secret private. Don't share it publicly or commit it to version control.

### 4.5 Download Credentials (Optional Backup)

1. On the Credentials page, find your newly created OAuth 2.0 Client ID
2. Click the download icon (⬇️) on the right
3. This downloads a JSON file as a backup

---

## Step 5: Configure WordPress Plugin

Now that you have your Google Cloud credentials, configure the WordPress plugin.

### 5.1 Navigate to Plugin Settings

1. Log in to your WordPress admin dashboard
2. Go to **Settings** → **Calendar Filter**

### 5.2 Enter OAuth Credentials

1. Find the **"Google Calendar Connection"** section
2. Enter your **Client ID** (from Step 4.4)
3. Enter your **Client Secret** (from Step 4.4)

### 5.3 Verify Redirect URI

Before saving, verify that the **Redirect URI** shown in the plugin settings matches exactly what you entered in Google Cloud Console (Step 4.3).

Should look like:
```
https://yoursite.com/wp-admin/admin.php?page=google-calendar-tag-filter-settings&gcal_oauth_callback=1
```

### 5.4 Connect to Google Calendar

1. Click **"Save and Connect with Google"**
2. You'll be redirected to Google
3. Log in with your Google account (if not already logged in)
4. Review the permissions:
   - The app will request **"See and download any calendar you can access using your Google Calendar"**
5. Click **"Allow"**
6. You'll be redirected back to WordPress
7. You should see a success message: **"Successfully connected to Google Calendar!"**

### 5.5 Select Calendar

1. A dropdown will appear with all your accessible calendars
2. Select the calendar you want to display events from
3. Click **"Save Calendar Selection"**

### 5.6 Test Connection

1. Click the **"Test Connection"** button
2. You should see an alert: **"Connection successful!"** with your calendar name and event count

---

## Troubleshooting

### Error: "Redirect URI Mismatch"

**Problem:** The redirect URI doesn't match what's configured in Google Cloud Console.

**Solution:**
1. Go back to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to **APIs & Services** → **Credentials**
3. Click on your OAuth 2.0 Client ID
4. Under **"Authorized redirect URIs"**, verify the URI **exactly matches** the one shown in WordPress plugin settings
5. Common issues:
   - Missing `https://`
   - Wrong domain
   - Extra/missing trailing slash
   - Typo in the path

### Error: "Access Blocked: This app's request is invalid"

**Problem:** OAuth consent screen not properly configured.

**Solution:**
1. Go to **APIs & Services** → **OAuth consent screen**
2. Verify your domain is listed under **"Authorized domains"**
3. Ensure your email is correct
4. If using "External" user type, add your email to **"Test users"**

### Error: "The app is blocked from accessing Google Calendar"

**Problem:** App is in testing mode and you're not a test user, or the app is blocked.

**Solution:**
1. Go to **OAuth consent screen**
2. Click **"+ ADD USERS"** under Test users
3. Add your Google account email
4. Try connecting again

### Error: "Calendar API has not been enabled"

**Problem:** The Google Calendar API isn't enabled for your project.

**Solution:**
1. Go to **APIs & Services** → **Library**
2. Search for "Google Calendar API"
3. Click **"ENABLE"**

### Error: "Invalid Client" or "Client Secret Mismatch"

**Problem:** Client ID or Secret was entered incorrectly.

**Solution:**
1. Go to **APIs & Services** → **Credentials**
2. Click on your OAuth 2.0 Client ID
3. Verify the Client ID
4. Click **"Reset Secret"** if needed (creates a new Client Secret)
5. Copy and re-enter in WordPress

### Can't Find OAuth Consent Screen

**Problem:** OAuth consent screen menu item doesn't appear.

**Solution:**
1. Ensure you've selected your project in the dropdown at the top
2. Try clearing your browser cache
3. Use an incognito window
4. Ensure you have proper permissions on the Google Cloud project

---

## Security Best Practices

### Protect Your Client Secret

- ✅ **DO** store it securely in your WordPress database (the plugin encrypts it)
- ✅ **DO** use HTTPS on your WordPress site
- ❌ **DON'T** commit it to version control (Git, etc.)
- ❌ **DON'T** share it publicly or in support forums
- ❌ **DON'T** hardcode it in theme files

### Rotate Credentials Regularly

If you suspect your credentials have been compromised:

1. Go to **APIs & Services** → **Credentials**
2. Click on your OAuth 2.0 Client ID
3. Click **"Reset Secret"**
4. Update the new secret in WordPress

### Limit Scopes

The plugin only requests `calendar.readonly` scope (read-only access). Never grant more permissions than necessary.

### Monitor API Usage

1. Go to **APIs & Services** → **Dashboard**
2. View your Calendar API usage
3. Set up quotas and alerts if needed

---

## Publishing Your App (Optional)

By default, your app is in "Testing" mode with a limit of 100 users. If you need more users:

### When to Publish

- You have a public WordPress site with many visitors
- You expect more than 100 unique Google accounts to connect
- You want to remove the "unverified app" warning

### Steps to Publish

1. Go to **OAuth consent screen**
2. Review all information is correct
3. Click **"PUBLISH APP"**
4. If using sensitive scopes (like calendar.readonly), you'll need to submit for verification:
   - Fill out the verification questionnaire
   - Explain how your app uses Calendar data
   - Provide demo video
   - Google will review (can take several days to weeks)

### Verification Requirements

For most small sites, you **don't need to publish** the app. Just add test users as needed.

---

## Additional Resources

- [Google Cloud Console](https://console.cloud.google.com/)
- [Google Calendar API Documentation](https://developers.google.com/calendar)
- [OAuth 2.0 Documentation](https://developers.google.com/identity/protocols/oauth2)
- [Google API Client Libraries](https://developers.google.com/api-client-library)

---

## Support

If you encounter issues:

1. Double-check all steps in this guide
2. Review the **Troubleshooting** section above
3. Check the [WordPress plugin support forum](https://github.com/infuseproduct/google-calendar-tag-filter/issues)
4. Enable WordPress debug mode to see detailed error messages

---

**Last Updated:** 2025-01-08
