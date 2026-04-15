# PWL Integración Fintoc (Lite)

WordPress plugin: WooCommerce + [Fintoc](https://fintoc.com/) payments.

**Stable tag:** 1.0.0

## Go-live checklist (Fintoc)

1. **API keys:** switch off test mode and use live secret keys in **PWL Fintoc** settings.
2. **Recipient account:** verify RUT, account number, type, and `institution_id` match your bank (see [Chile institution codes](https://docs.fintoc.com/reference/chile-institution-codes)).
3. **WooCommerce currency:** store currency must be CLP or MXN for the gateway to be available.
4. **Thank-you page (Lite):** ensure customers can reach the order-received page so the plugin can refresh the checkout session.
5. **Webhooks (Pro):** in the Fintoc Dashboard, register endpoint `https://YOUR-DOMAIN/wp-json/pwl-fintoc/v1/webhook` (replace with your site URL), subscribe to payment events, and paste the **webhook signing secret** into the plugin. Use the same mode (test/live) as your API keys.
6. **TLS:** site must use HTTPS for production webhooks.
7. **Idempotency:** events are deduplicated by Fintoc event `id` in the local log table.

## Lite vs Pro

| Feature | Lite | Pro |
|--------|------|-----|
| Checkout session + redirect | Yes | Yes |
| Confirm on order-received (API refresh) | Yes | Yes |
| Signed webhooks + order updates | No | Yes |
| Webhook debug log | No | Yes |
| Refunds from WooCommerce | No | Yes |

## Build

From the plugin root:

```bash
npm run build:lite   # releases/lite/pwl-integracion-fintoc/
npm run build:pro    # releases/pro/pwl-integracion-fintoc-pro/
```
