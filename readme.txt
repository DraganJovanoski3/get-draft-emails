=== Draft Orders – Get Customer Emails ===
Contributors: custom
Tags: woocommerce, email marketing, abandoned checkout, draft orders
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Capture customer emails from WooCommerce draft/checkout-draft orders for email marketing.

== Description ==

Standalone admin tool to export customer emails from WooCommerce draft orders.

This plugin is intentionally isolated:

* No WooCommerce hooks on checkout, cart, or orders
* No background cron jobs
* No WooCommerce compatibility registration
* Only loads WooCommerce code when you click Sync in the admin

== Changelog ==

= 1.2.0 =
* Complete isolation rewrite — zero interference with other plugins
* Removed all checkout hooks, cron jobs, and auto-capture
* WooCommerce is only touched during manual Sync button click
* Simplified to a single admin tool under Tools menu

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
