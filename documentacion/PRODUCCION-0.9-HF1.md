# Sector Sign 0.9.0 HF1 — Producción: NO-GO hasta validación en servidor y dispositivos
Este paquete no declara producción. La versión 0.9.0 continúa marcada como beta.
## Cambios revisados
- Estado de Inventario Pro en el panel: nunca, reciente, retrasado, desactualizado y error de reloj. Es el último envío autenticado, **no** prueba de conexión permanente.
- `php server/tools/production-preflight.php --json --require-inventory --require-device`: diagnóstico de solo lectura de PHP/MySQL, HTTPS, tablas, disco, sincronización, backup y dispositivos.
- Pruebas del estado de sincronización añadidas a QA.
## Requisitos imprescindibles
1. Ejecutar aaPanel con dominio HTTPS y raíz pública estrictamente `server/public`. Base MySQL exclusiva y permisos por organización.
2. Hacer copia **verificada** antes de migrar: MySQL, configuración, multimedia y clave de recuperación; restaurar en una instalación vacía.
3. **Inventario Pro 360**: adaptar el exportador al **ZIP vigente real**. La API HMAC ya recibe catálogos, pero no lee automáticamente la instalación de Inventario. No modificar datos fiscales, compras, costos, margen ni stock reservado; exportar exclusivamente datos de venta públicos.
4. Confirmar un producto nuevo, cambio de precio, campaña vencida/deshabilitada, reintento idempotente, importación firmada y alerta de sincronización antigua. Mantener `auto_publish=false` hasta aprobar estas pruebas.
5. Vincular un H96 real, enviar una campaña con imagen, MP4 y narración, cortar internet 10 minutos, reiniciar y verificar que conserva reproducción. Probar sonido/TTS disponible en el equipo.
6. Para múltiples clientes: cuotas, permisos por empresa, políticas de retención, consentimiento de datos, alertas de fallos, reportes y restauración completa. Dimensionar S3/CDN con pruebas de carga antes de ofrecer SLA.
## Sugerencias posteriores al piloto
Prueba de reproducción por anuncio, portal para anunciantes con aprobación, panel de facturación de pantallas, plantillas por rubro, avisos de pantalla desconectada, voz sintetizada prerenderizada, QR de campaña y actualización automática firmada.
