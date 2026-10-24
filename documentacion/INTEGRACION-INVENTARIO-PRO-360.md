# Inventario Pro 360 → Sector Sign 0.8: sólo catálogo comercial público

El inventario real utiliza PHP modular y almacenes propios. Sector Sign **no** debe acceder a la base, `app/storage`, Kardex, compras, ventas, proveedores ni emisión fiscal de Inventario Pro 360.

- Sector Sign → Inventario → Crea una integración, con ID y clave HMAC, guardada sólo en el servidor.
- Inventario Pro debe exponer o generar un JSON privado **exclusivamente comercial** con ID público estable, nombre, presentación, precio público, promoción, habilitado y una revisión monotonamente creciente por producto. Debe ser aprobado por el usuario antes de enviar.
- Cron privado en aaPanel: `php integracion-inventario-pro-360/bin/enviar-catalogo-publico.php /ruta/privada/catalogo-publico.json` con variables `SECTOR_SIGN_URL`, `SECTOR_SIGN_KEY_ID`, `SECTOR_SIGN_SECRET` de entorno.
- Los cambios generan creatividades nuevas con precio público. Si se habilita publicación automática en el panel, se actualiza sólo la lista de promociones y las pantallas que ya tenían esa lista publicada.
- HMAC SHA-256, marcas temporales y nonces protegen contra falsificación y repetición.

**Estado real:** contrato y cliente de importación implementados y probados; **no está conectado al ZIP exacto del Inventario Pro 360 instalado en el VPS**, porque ese ZIP no está disponible. No editar Inventario Pro basándose en documentación vieja o rutas supuestas.
