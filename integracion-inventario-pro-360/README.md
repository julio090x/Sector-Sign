# Conector Inventario Pro 360 — Sector Sign 0.8.0

Conector **independiente, de solo lectura comercial**, que no toca documentos, ventas, compras, caja, stock, costos, impuestos, proveedores ni credenciales de la plataforma. `src/SectorSignPublisher.php` exige URL HTTPS y envía un JSON firmado con HMAC-SHA256.

## Uso de ejemplo

1. Crea una conexión en Sector Sign → Inventario y copia el ID y la clave secreta (visible solo una vez).
2. Inspecciona la base **real** y actual de Inventario Pro 360 antes de conectar su exportador; esta entrega NO modifica tu ZIP canónico.
3. Obtén de la API o catálogo público del inventario: ID, nombre, presentación, precio público, cantidad mínima, estado promocional y número de revisión actualizado cuando cambie el precio.
4. En un proceso privado/CLI, utiliza el ejemplo en `examples/enviar-publicidad.php` tras sustituir el JSON de prueba.
5. Ejecuta `php tests/contract.php` para validar el contrato de datos públicos y firma.

**No subas el ejemplo de productos a producción**: sus registros son demostrativos, no datos reales. Usa variables o archivos de configuración privados para la URL, Key ID y Secret.

## Sincronización con un JSON comercial (Cron privado)

El nuevo `bin/enviar-catalogo-publico.php` lee un archivo **JSON comercial ya autorizado** que Inventario Pro 360 exporte en un directorio privado (nunca su base financiera/stock). El script no necesita conectarse directamente a otra base de datos.

```bash
SECTOR_SIGN_URL='https://sign.ejemplo.com' \
SECTOR_SIGN_KEY_ID='ID_OBTENIDO_DEL_PANEL' \
SECTOR_SIGN_SECRET='SECRETO_PRIVADO' \
php bin/enviar-catalogo-publico.php /ruta/privada/catalogo-publico.json
```

Programa el comando en aaPanel Cron cuando tengas configurado el exportador del **ZIP vigente real** de Inventario Pro. Hasta entonces es un conector interoperable probado por contrato, **no una conexión en vivo a tus productos reales**. No abras un puerto MySQL de Inventario ni pongas las claves en GitHub.
