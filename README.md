# Thawani Payment Gateway for WooCommerce

Accept Visa and MasterCard payments on WooCommerce stores via [Thawani Pay](https://thawani.om).

## About

This plugin was originally a **paid** product developed by [The Source for Development (s4d.om)](https://www.s4d.om). It has since been **rewritten from scratch with [Claude Code](https://claude.com/claude-code)** against Thawani's current v1 API and **released as open source** under the GPL.

The legacy database table (`{prefix}thawani_invoice_map`) and the legacy settings option key (`woocommerce_thawani_settings`) are preserved, so installations upgrading from the original paid version keep their settings and historical data without migration.

## Features

- **Hosted checkout** — customers are redirected to Thawani's secure checkout page (PCI scope stays with Thawani).
- **Saved cards** — logged-in customers can store cards on Thawani and reuse them. Cards live on Thawani; only the token is referenced.
- **Refunds** — full refunds from the WooCommerce admin. The plugin resolves the real `payment_id` via Thawani's `/payments` endpoint (Thawani does not support partial refunds).
- **Webhooks** — optional server-to-server event endpoint at `/wc-api/thawani_webhook` so payment status syncs even if the customer closes the page before being redirected back. Optional HMAC-SHA256 signature verification.
- **WooCommerce Blocks** — supports the new block-based checkout in addition to the classic shortcode checkout.
- **HPOS compatible** — declares compatibility with WooCommerce's High-Performance Order Storage and the Cart/Checkout Blocks feature.
- **Currency conversion hook** — non-OMR stores can hook `thawani_gateway_convert_to_omr` to convert amounts at checkout time.
- **Vendor metadata** — when Dokan is installed, vendor IDs/names are included in the Thawani metadata payload.
- **Debug logging** — toggleable per-request logging via the WooCommerce logger (source: `thawani`).

## Requirements

- PHP 8.1+
- WordPress 6.2+
- WooCommerce 7.0+
- A Thawani merchant account (UAT credentials work out of the box for testing)

## Installation

1. Download or clone this repository into `wp-content/plugins/thawani-payment-gateway-for-woocommerce`.
2. Activate **Thawani Payment Gateway for WooCommerce** from the Plugins screen.
3. Go to **WooCommerce → Settings → Payments → Thawani Gateway** and enter your keys.
4. (Optional) Copy the **Webhook URL** shown in settings into your Thawani portal so payment status reconciles when customers don't return to the site.

## Configuration

| Setting | Notes |
| --- | --- |
| Test mode | Uses Thawani's UAT environment. Defaults populated with public UAT keys. |
| Publishable / Secret keys | Production credentials from your Thawani dashboard. |
| Show Order ID at checkout | Sends a single line item ("Order #123") instead of per-product breakdown. Auto-enables when item amounts drop below 100 baisa or when discounts apply. |
| Allow saving cards | Lets logged-in customers tokenize cards on Thawani for future purchases. |
| Webhook URL | Read-only. Paste into the Thawani portal under Webhooks. |
| Webhook Secret | Optional HMAC-SHA256 secret. When set, incoming webhooks must include a matching `Thawani-Signature` header. |
| Debugging | Logs request/response payloads to the `thawani` log channel. |

## Refund flow

WooCommerce's refund button issues a full refund (Thawani does not support partials). The plugin:

1. Resolves the real `payment_id` by calling `GET /payments?checkout_invoice=…` (or `payment_intent=…` for saved-card orders).
2. Calls `POST /refunds` with that payment_id, the reason, and order metadata.
3. Adds an order note containing the Thawani refund id.

Partial refund attempts are rejected with a clear admin error.

## Development

The codebase is intentionally small and PSR-4 autoloaded under the `S4D\Thawani` namespace from `src/`.

```
src/
├── Ajax/CardController.php          # AJAX handler for deleting saved cards
├── Api/Client.php                   # Thawani HTTP client (sessions, intents, payments, refunds)
├── Blocks/PaymentMethodIntegration.php
├── Gateway.php                      # WC_Payment_Gateway implementation
├── Installer.php                    # Activation / DB schema
├── Plugin.php                       # Bootstraps hooks
└── Webhook/
    ├── EventController.php          # Real server-to-server webhook (POST /wc-api/thawani_webhook)
    └── WebhookController.php        # Customer redirect handler (GET /wc-api/thawani)
```

Lint with `php -l` per file. There is no build step.

## Credits

- Originally developed (paid) by [The Source for Development](https://www.s4d.om).
- Rewritten and open-sourced with the help of [Claude Code](https://claude.com/claude-code).

## License

GPLv2 or later. See [LICENSE](LICENSE) if present, or the standard WordPress plugin GPL terms.
