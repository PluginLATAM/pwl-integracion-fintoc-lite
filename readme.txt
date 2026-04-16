=== PWL Integración Fintoc ===
Contributors: pluginlatam
Tags: woocommerce, fintoc, payments, chile, transfer
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Fintoc bank transfers for WooCommerce (CLP/MXN). Lite syncs on thank-you; Pro: webhooks, refunds, and order tools.

== Description ==

This plugin is developed independently by PluginLATAM and is not affiliated with, endorsed by, or sponsored by Fintoc. It uses Fintoc’s public APIs as described in the [Fintoc documentation](https://docs.fintoc.com/docs/welcome).

**PWL Integración Fintoc** connects WooCommerce to [Fintoc](https://fintoc.com/) checkout sessions: customers pay on Fintoc-hosted pages, then return to your store.

* Direct deposit to your Chilean recipient account (RUT, account number, institution).
* Test and live API keys.
* **Lite:** order status is updated when the customer returns to the order-received page (session refreshed via API).
* **Pro:** signed webhooks so WooCommerce can sync when Fintoc notifies your site (checkout session and payment intents—including pending/rejected/expired—plus refund and treasury notes when an order can be matched), a webhook debug log with copyable endpoint URL, refunds from the order screen, **Fintoc** metabox on orders with “Refresh from Fintoc”, payment summary on the customer order view, and stored transaction metadata (reference, dates, accounts JSON in admin).

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin ZIP or install from this package.
3. Activate **PWL Integración Fintoc**.
4. Go to **PWL Fintoc** in the admin menu: enter your Fintoc secret key (test or live) and recipient account details.
5. Enable the **Fintoc** payment method under WooCommerce → Settings → Payments.

== Frequently Asked Questions ==

= Is this an official Fintoc plugin? =

No. PluginLATAM built this integration using Fintoc’s public API. “Fintoc” is a trademark of its respective owner.

= Which currencies are supported? =

CLP and MXN, per Fintoc availability.

= Where do I configure webhooks (Pro)? =

In the **Fintoc Dashboard**, create a webhook endpoint whose URL matches the REST URL on **PWL Fintoc → Webhook log** (copy button) or **PWL Fintoc** settings. Use the same test or live mode as your API keys. Paste the signing secret (shown once when you create the endpoint) into the plugin so incoming requests can be verified via the `Fintoc-Signature` header. The settings screen explains which event types the plugin handles.

= How is the Pro webhook endpoint secured? =

The REST route must accept POST requests from Fintoc without a logged-in WordPress user. Security relies on cryptographic verification: each request must include a valid `Fintoc-Signature` or `fintoc_signature` header checked against your webhook signing secret from the Fintoc Dashboard. Invalid or missing signatures receive HTTP 400.

== External services ==

This plugin connects to the **Fintoc API** at `https://api.fintoc.com` over HTTPS to create and retrieve checkout sessions, read payment state, retrieve payment intents when staff use “Refresh from Fintoc” on an order, and perform refunds where supported. Integration behavior follows [Fintoc’s official documentation](https://docs.fintoc.com/docs/welcome). The **Pro** edition also exposes a WordPress REST URL where **Fintoc’s servers** send webhook events; those requests are authenticated with the signing secret you configure, not WordPress cookies.

**Data sent to Fintoc:** Your configured secret API key is sent in the `Authorization` header. JSON request and response bodies may include checkout session IDs, payment intent identifiers, amounts, currency, recipient/bank details you configure, and metadata needed to link payments to WooCommerce orders.

**When:** During checkout, when the customer lands on the thank-you page (Lite session refresh), when staff refresh a payment intent from the order screen (Pro), when processing refunds from WooCommerce (where supported), and whenever Fintoc delivers webhook events to your site (Pro).

**Fintoc legal pages (no affiliate links; use current URLs from [fintoc.com](https://fintoc.com/) if these change):**

* [Fintoc documentation (API and guides)](https://docs.fintoc.com/docs/welcome)
* Chile — Terms: https://fintoc.com/cl/legal/terminos-y-condiciones
* Chile — Terms (developer section tab): https://fintoc.com/cl/legal/terminos-y-condiciones?tab=user-priv
* Chile — Privacy policy: https://fintoc.com/cl/legal/politica-de-privacidad
* Mexico — Terms: https://fintoc.com/mx/legal/terminos-y-condiciones
* Mexico — Privacy policy: https://fintoc.com/mx/legal/politica-de-privacidad

== Changelog ==

= 1.0.1 =
* Developer: extension hooks use the prefix `pwlintegracionfintoc_*` (lowercased PHP namespace; matches WordPress.org Plugin Check prefix scanner).
* Developer: added `phpcs.xml.dist` (ValidHookName + PrefixAllGlobals); run PHPCS with WPCS locally (coding-standard packages are not shipped in `vendor/`).

= 1.0.0 =
* Initial release: WooCommerce gateway, Lite return-path confirmation, Pro webhooks and refunds.
* Pro: transaction snapshot and order metabox, refresh payment intent from admin, customer order summary, expanded webhook types (session expired, more payment_intent states, payout/transfer notes when correlatable).
* Developer: extension hooks use the prefix `pwlintegracionfintoc_*`.
