# Conector Inventario Pro 360 — Sector Sign 0.4.0

Conector **independiente, de solo lectura comercial**, que no toca documentos, ventas, compras, caja, stock, costos, impuestos, proveedores ni credenciales de la plataforma. `src/SectorSignPublisher.php` exige URL HTTPS y envía un JSON firmado con HMAC-SHA256.

## Uso de ejemplo

1. Crea una conexión en Sector Sign → Inventario y copia el ID y la clave secreta (visible solo una vez).
2. Inspecciona la base **real** y actual de Inventario Pro 360 antes de conectar su exportador; esta entrega NO modifica tu ZIP canónico.
3. Obtén de la API o catálogo público del inventario: ID, nombre, presentación, precio público, cantidad mínima, estado promocional y número de revisión actualizado cuando cambie el precio.
4. En un proceso privado/CLI, utiliza el ejemplo en `examples/enviar-publicidad.php` tras sustituir el JSON de prueba.
5. Ejecuta `php tests/contract.php` para validar el contrato de datos públicos y firma.

**No subas el ejemplo de productos a producción**: sus registros son demostrativos, no datos reales. Usa variables o archivos de configuración privados para la URL, Key ID y Secret.
