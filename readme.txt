=== Thawani Payment Gateway for WooCommerce ===
Contributors: phpawcom, s4d
Tags: woocommerce, payment-gateway, thawani, oman, omr
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Visa and MasterCard payments on WooCommerce stores via Thawani Pay.

== Description ==

Originally a paid plugin developed by The Source for Development (https://www.s4d.om), rewritten with Claude Code and released as open source.

Features:

* Hosted Thawani checkout — customers redirect to Thawani's secure checkout page; PCI scope stays with Thawani.
* Saved cards — logged-in customers can tokenize cards on Thawani and reuse them at checkout.
* Refunds — full refunds from the WooCommerce admin (Thawani does not support partial refunds).
* Webhooks — optional server-to-server endpoint so payment status syncs even if the customer closes the page before redirect. Optional HMAC-SHA256 signature verification.
* WooCommerce Blocks support and HPOS compatibility.
* Currency conversion hook (thawani_gateway_convert_to_omr) for non-OMR stores.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install via the Plugins screen.
2. Activate the plugin.
3. Go to WooCommerce > Settings > Payments > Thawani Gateway and enter your Thawani API keys.
4. (Optional) Copy the Webhook URL shown in the settings into your Thawani portal under Webhooks.

== Frequently Asked Questions ==

= Does Thawani support partial refunds? =

No. Refund attempts that don't match the order total are rejected with a clear error.

= Will my legacy settings carry over? =

Yes. The settings option key (woocommerce_thawani_settings) and the legacy mapping table ({prefix}thawani_invoice_map) are preserved.

= How do I test? =

Enable Test mode in the gateway settings. The form ships with public Thawani UAT credentials.

== Changelog ==

= 2.0.0 =
* First open-source release. Rewrite of the paid plugin against Thawani's v1 API.
* Refunds now resolve the real payment_id via GET /payments before calling /refunds.
* Added a server-to-server webhook endpoint with optional HMAC signature verification.
* WooCommerce Blocks and HPOS compatibility.

== Upgrade Notice ==

= 2.0.0 =
First open-source release. Existing settings and historical data are preserved without manual migration.
