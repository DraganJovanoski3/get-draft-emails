=== Draft Orders – Get Customer Emails ===
Contributors: custom
Tags: woocommerce, email marketing, abandoned checkout, draft orders
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Capture customer emails from WooCommerce draft/checkout-draft orders for email marketing.

== Description ==

When customers use the WooCommerce block checkout, WooCommerce creates a **draft order** as soon as they reach checkout. If they enter their billing email but leave without paying, that email is stored on the draft order — but WooCommerce automatically deletes draft orders after about 24 hours.

This plugin:

* Captures emails (and name, phone, cart total) from draft orders in real time
* Stores them in your database so they are not lost when drafts are cleaned up
* Marks leads as "converted" if the customer later completes the order
* Provides an admin page under **WooCommerce → Draft Order Emails**
* Exports leads to CSV for Mailchimp, Klaviyo, etc.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or copy from your development folder.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce → Draft Order Emails** to view and export captured leads.

== Frequently Asked Questions ==

= Does this work with classic checkout? =

Yes. Emails are captured whenever a draft order is created or updated with billing details.

= Will I get duplicate emails? =

Each draft order is stored once. If the same customer abandons multiple times, you may see multiple entries (one per draft order).

= Which leads should I use for marketing? =

Filter by **Abandoned only** to exclude customers who later completed their purchase.

== Changelog ==

= 1.1.0 =
* Renamed to "Draft Email Collector" — no longer registers with WooCommerce compatibility system
* Removed WC tested up to header and background cron on every page load
* Added Pause switch (turn off without deactivating plugin)
* Auto-capture is OFF by default — manual sync only unless you enable it
* Admin page moved to Tools → Draft Order Emails
* Added uninstall.php for clean removal

= 1.0.2 =
* Full bulk sync for all draft orders (was limited to 100)
* Fast SQL scan for orders with billing email (HPOS + legacy)
* Better email detection (billing meta, logged-in customer email)
* Also imports unpaid pending/failed/cancelled orders with email
* Sync shows scanned/captured/skipped counts

= 1.0.1 =
* Declare WooCommerce HPOS and Cart/Checkout Blocks compatibility
* Fix order edit links when HPOS is enabled

= 1.0.0 =
* Initial release
