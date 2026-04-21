# WP Easy Redirect

**Redirect your WordPress site — no hosting access needed.**

WP Easy Redirect is a lightweight WordPress plugin that lets you set up redirects entirely from within the WordPress admin panel. Perfect for when you don't have FTP, cPanel, or server-level access.

## Features

- **Global Site Redirect** — Redirect the entire front-end to a new domain
- **Path Preservation** — Optionally keep URL paths (e.g. `old.com/about` → `new.com/about`)
- **Individual Redirect Rules** — Redirect specific pages/posts to new URLs
- **Wildcard Support** — Use `*` in "From" and `$1` in "To" (e.g. `/blog/*` → `/news/$1`)
- **Multiple Redirect Types** — 301, 302, 307, and 308
- **Exclude Logged-In Users** — Let logged-in visitors browse normally
- **Admin Bar Indicator** — See when a global redirect is active
- **Self-Redirect Safety** — Automatically disables redirect if the target is the same site

## Installation

1. Download or clone this repository
2. Zip the `wp-easy-redirect` folder → `wp-easy-redirect.zip`
3. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
4. Upload the zip and activate

Or manually: copy the `wp-easy-redirect` folder into `/wp-content/plugins/` and activate.

## Usage

### Global Redirect

1. Go to **Settings → WP Redirect**
2. Enter the **Target URL** (e.g. `https://new-site.example.com`)
3. Toggle **Enable Redirect** on
4. Click **Save Global Redirect**

All front-end visitors will now be redirected. The WordPress admin (`/wp-admin/`) is never redirected.

### Individual Redirects

1. Go to **Settings → WP Redirect**
2. Under **Individual Redirect Rules**, click **+ Add Redirect Rule**
3. Enter the **From** path (e.g. `/old-page`) and **To** URL (e.g. `https://new.com/page`)
4. Choose the redirect type (301 by default)
5. Click **Save Redirect Rules**

### Wildcard Redirects

Use `*` as a wildcard in the From field and `$1` to reference the matched portion:

| From | To | Effect |
|------|----|--------|
| `/blog/*` | `https://new.com/news/$1` | `/blog/hello` → `/news/hello` |
| `/old-category/*` | `https://new.com/category/` | Match anything under `/old-category/` |

## Safety Features

- **Admin is never redirected** — `wp-admin`, cron, and REST API requests bypass the redirect
- **Self-redirect detection** — If you accidentally set the target to your own site, the redirect is disabled automatically
- **Off by default** — The plugin starts disabled so activating it won't break your site

## File Structure

```
wp-easy-redirect/
├── wp-easy-redirect.php         # Main plugin file
├── includes/
│   ├── class-settings.php       # Settings CRUD helpers
│   ├── class-redirect.php       # Core redirect logic
│   └── class-admin-page.php     # Admin settings page UI
├── assets/
│   ├── admin.css                # Admin styles
│   └── admin.js                 # Dynamic row add/remove
└── README.md
```

## Frequently Asked Questions

### Will I lose access to my admin panel?

No. The plugin explicitly skips redirects for `wp-admin`, cron requests, and REST API requests.

### Can I redirect only specific pages?

Yes. Leave the global redirect disabled and add Individual Redirect Rules for each page.

### What's the difference between 301 and 302?

- **301** — Permanent redirect. Search engines will update their index to the new URL.
- **302** — Temporary redirect. Search engines keep the original URL in their index.

## License

GPL-2.0+. See LICENSE for details.