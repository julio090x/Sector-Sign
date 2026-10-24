# Integrar Inventario Pro 360 (sin tocar módulos operativos ni fiscales)

**El ZIP real vigente de Inventario Pro 360 NO está adjunto en esta conversación.** Este adaptador está preparado y probado en contrato (campos permitidos/HMAC), pero la conexión específica a sus repositorios PHP/JSON se realizará sobre el ZIP canónico que uses, **después de inspeccionarlo**. No inventamos rutas ni modificamos una versión antigua por memoria.

1. En Sector Sign → **Inventario** crea una conexión y copia `Key ID` y `Secret` (se muestra solo una vez).
2. En la configuración privada de Inventario Pro coloca URL de Sector Sign, Key ID y Secret. **Nunca** en código público ni en la tienda/documentos.
3. El adaptador `SectorSignPublisher.php` toma solo: `id`, `name`, `presentation`, `public_price`, `public_min_quantity`, `public_promotion` y `public_updated_at/sequence`. Otros campos del registro fuente (costos, stock, documentos, clientes, bancos) **son descartados**.
4. El exportador consulta **solo** un catálogo comercial público autorizado mediante la capa de lectura existente; no abre una conexión a la base de Sector Sign ni escribe en Inventario Pro 360.
5. Envía POST firmado HMAC-SHA256 con `X-SS-Key`, `X-SS-Timestamp`, `X-SS-Nonce`, `X-SS-Signature`; se rechazan solicitudes de más de 5 minutos, repetidas o sin firma correcta.
6. Sector Sign crea creatividades SVG seguras, con precio público en soles. La imagen queda en su biblioteca. Si activas **Publicar automáticamente**, genera la lista de promociones y actualiza solo pantallas que ya usaban esa lista. Nada se envía a una pantalla nueva sin una primera asignación manual.
7. Los precios viejos no modifican archivos ya publicados; cada revisión crea nuevo recurso. Con almacenamiento externo se recomienda limpieza/retención adecuada.

**Revisión incremental:** envía un `sequence` entero creciente por producto (o `public_updated_at` que cambie al modificar su precio). Si el inventario emite la misma revisión una segunda vez, el importador la ignora. No conviene crear una nueva revisión en cada cron si el precio no cambió.

Ejemplo desde CLI (no exponer por HTTP):

```bash
export SECTOR_SIGN_URL='https://sign.ejemplo.com'
export SECTOR_SIGN_KEY_ID='COPIAR_DE_LA_CONSOLA'
export SECTOR_SIGN_SECRET='CLAVE_PRIVADA'
php examples/enviar-publicidad.php
```

**Contrato y prueba:** `php tests/contract.php` (en el ZIP de integración).
