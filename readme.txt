=== WP Lead Download ===
Contributors: yourname
Tags: lead generation, download gate, otp, email capture, lead magnet
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Capture leads via OTP-verified download gate.

== Description ==

WP Lead Download gates file downloads behind a name/email/phone form with OTP email verification.
Every successful download is recorded as a lead in the WordPress admin.

= Usage =

1. Activate the plugin
2. Go to **Lead Downloads → Add New**, fill in File URL, Button Label, Color, Form Title,
   Thank You Message, then Publish.
3. Copy the shortcode shown on the edit screen: `[lead_download id="X"]`
4. Paste it on any page or post.

= User Flow =

Button click → Fill Name / Email / Phone → OTP sent to email →
Enter 6-digit code → File auto-downloads in new tab →
Lead saved in admin panel

= Admin Features =

* **All Leads** — searchable, filterable by download, sortable, bulk delete, CSV export
* **Unread badge** — "All Leads" menu item shows count of new leads since last visit
* **Download Statistics** — total lead count + last 5 leads shown in the CPT edit screen sidebar
* **Settings** — admin notification email, user confirmation email, SMTP override,
  OTP expiry, cooldown, max hourly OTP attempts, uninstall data option
* **Send Test Email** — one-click test button on the Settings page to verify mail config

= Security =

* `random_int()` for OTP generation (CSPRNG)
* OTP verified server-side: `is_used = 0 AND expires_at > NOW()`
* OTP never returned to the browser — only the file URL after successful verification
* Per-email per-download cooldown (configurable) AND hourly attempt cap (brute-force protection)
* File URL validated with `filter_var(FILTER_VALIDATE_URL)` before any lead is written
* Duplicate lead prevention: same email + download can only produce one lead row per day
* Nonce refreshed on demand — survives full-page caches (WP Rocket, LiteSpeed, W3TC)
* All AJAX handlers protected with `check_ajax_referer()`
* All admin actions protected with `current_user_can('manage_options')`
* All DB queries use `$wpdb->prepare()`

= Accessibility =

* Modal uses `role="dialog"` and `aria-modal="true"`
* Error regions use `role="alert"` and `aria-live="polite"`
* Focus is trapped inside the modal and restored to the trigger button on close
* OTP input has `aria-label` and `aria-required` attributes

== Changelog ==

= 1.0.0 =
* Initial release
