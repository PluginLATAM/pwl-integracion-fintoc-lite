# PWL Integración Fintoc (Lite)

Complemento para WordPress: WooCommerce + pagos a través de la API de [Fintoc](https://fintoc.com/).

> **Aclaración:** este proyecto **no es Fintoc** ni está afiliado oficialmente a Fintoc. Es un complemento de terceros (**PluginLATAM / PWL**) que **solo integra** la API pública de Fintoc en WooCommerce. Soporte del complemento: repositorio y autores del plugin. Dudas sobre cuentas, contratos o el producto Fintoc: [Fintoc](https://fintoc.com/).

**Documentación de integración:** [docs.fintoc.com — Welcome](https://docs.fintoc.com/docs/welcome)

**Enlaces legales (Chile):** [Términos y condiciones](https://fintoc.com/cl/legal/terminos-y-condiciones) · [Política de privacidad](https://fintoc.com/cl/legal/politica-de-privacidad) · [Términos (pestaña desarrolladores)](https://fintoc.com/cl/legal/terminos-y-condiciones?tab=user-priv)

**Stable tag:** 1.0.3

## Checklist antes de producción (Fintoc)

1. **Claves API:** desactiva el modo de prueba y usa las claves secretas **en vivo** en los ajustes de **PWL Fintoc**.
2. **Cuenta receptora:** comprueba que el RUT, el número de cuenta, el tipo y el banco sean los correctos.
3. **Moneda en WooCommerce:** la tienda debe usar CLP o MXN para que el método de pago esté disponible.
4. **Página de gracias (Lite):** asegúrate de que los clientes lleguen a la página de pedido recibido para que el complemento pueda actualizar la sesión de checkout.
5. **Webhooks (Pro):** en el panel de Fintoc, registra el endpoint `https://TU-DOMINIO/wp-json/pwl-fintoc/v1/webhook` (sustituye por la URL de tu sitio), suscríbete a eventos de pago y pega el **secreto de firma del webhook** en el complemento. Usa el mismo modo (prueba / producción) que tus claves API.
6. **TLS:** en producción, el sitio debe usar HTTPS para los webhooks.
7. **Idempotencia:** los eventos se deduplican por el `id` del evento de Fintoc en la tabla de registro local.

## Lite vs Pro

| Funcionalidad | Lite | Pro |
|---------------|------|-----|
| Sesión de checkout + redirección | Sí | Sí |
| Confirmación en pedido recibido (actualización vía API) | Sí | Sí |
| Webhooks firmados + actualización de pedidos | No | Sí |
| Registro de depuración de webhooks | No | Sí |
| Devoluciones desde WooCommerce | No | Sí |

## Changelog

### 1.0.2

- **Admin:** panel Resumen (Overview): KPIs, últimos payment intents (API Fintoc), vista previa de webhooks en Pro; los ajustes pasan a un submenú «Ajustes»; diseño más compacto.
- **i18n:** cadenas en español (Chile) para el panel y el menú.
- **Desarrollo:** `Client::list_payment_intents()` para `GET /v1/payment_intents` (panel; con caché transitoria).

### 1.0.1

- **Desarrolladores:** hooks de extensión con prefijo `pwlintegracionfintoc_*` (namespace PHP en minúsculas; compatible con Plugin Check en WordPress.org).
- **Desarrolladores:** se añadió `phpcs.xml.dist`; PHPCS + WPCS en local (no se incluyen en `vendor/` del paquete).

### 1.0.0

- Versión inicial: pasarela WooCommerce, confirmación Lite en página de gracias, webhooks y devoluciones Pro, herramientas de pedido y snapshot de transacción en Pro.
