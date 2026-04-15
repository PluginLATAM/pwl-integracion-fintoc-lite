# PWL Integración Fintoc (Lite)

Complemento para WordPress: WooCommerce + pagos con [Fintoc](https://fintoc.com/).

**Stable tag:** 1.0.0

## Checklist antes de producción (Fintoc)

1. **Claves API:** desactiva el modo de prueba y usa las claves secretas **en vivo** en los ajustes de **PWL Fintoc**.
2. **Cuenta receptora:** comprueba que el RUT, el número de cuenta, el tipo y el `institution_id` coincidan con tu banco (ver [códigos de institución en Chile](https://docs.fintoc.com/reference/chile-institution-codes)).
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

## Build

Desde la raíz del complemento:

```bash
npm run build:lite   # releases/lite/pwl-integracion-fintoc/
npm run build:pro    # releases/pro/pwl-integracion-fintoc-pro/
```

`build:lite` copia los archivos actualizados **dentro de** `releases/lite/pwl-integracion-fintoc/` y **no modifica** un **`.git`** que ya exista (clona o inicializa el repo Lite de GitHub ahí una vez).
