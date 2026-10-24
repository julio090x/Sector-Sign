# Sector Sign 0.7.0

Dos productos: **Centro de Mando** y **Reproductor**, con una interfaz web compartida y servidor PHP/MySQL propio para aaPanel. No requiere Node, Composer ni Docker para operar. Las herramientas Android/Angular solo son para compilar/desarrollar.

## Novedades 0.7.0

- Programación de una o varias listas por franja, días, fechas y zona horaria.
- Rotación completa de las listas en el orden elegido; prioridades en solapes y lista de respaldo.
- Publicaciones inmutables: descarga íntegra antes de activar; horarios resueltos en el reproductor sin consultar internet.
- Dispositivos: contacto reciente, versión web y APK, resolución, plataforma, franja/listas, anuncio reportado, caché y progreso por archivos.
- Dos APK generadas desde el mismo proyecto Android; selector de archivos para el panel y metadatos de versión/modelo.
- Editor responsive con controles ES/EN/PT y motor de simulación compartido.

## Directorios

`server/`: aplicación operativa PHP/MySQL y web responsive. `android-unificado/`: dos variantes del mismo contenedor Android. `angular-unificado/`: shell Angular en migración, que reutiliza los mismos archivos web; no es una conversión Angular completa. `integracion-inventario-pro-360/`: contrato comercial HMAC, sin datos reales. `documentacion/`: instalación, cambios y límites. `scripts/`: verificaciones.

## Instalar o actualizar

Lea `LEEME-PRIMERO.md` y `documentacion/ACTUALIZAR-0.7.0.md`. La raíz del sitio debe apuntar a `server/public`, nunca al directorio superior. Instale en un subdominio de pruebas separado de Inventario Pro 360. La migración añade dos tablas sin borrar las existentes.

## Android

La compilación de las dos variantes está automatizada en `.github/workflows/build-two-android-apps.yml`. Genera APK de evaluación y release sin firmar. Las APK release entregadas al propietario se firmaron fuera de GitHub con una clave privada entregada por separado. **Nunca subir un JKS, contraseñas, config.php, datos, multimedia privada ni respaldos**. Para actualizar una APK instalada use la misma clave y aumente `versionCode`.

## Alcance verificable

Compilación real Android: https://github.com/julio090x/Sector-Sign/actions/runs/40092956507

Las APK son contenedores ligeros WebView: cargan la misma web del servidor configurado. No contienen un servidor MySQL ni todos los anuncios. El primer acceso necesita conexión. La caché offline completa y el reinicio sin red requieren HTTPS y un WebView/navegador con las capacidades correspondientes. No se ha certificado físicamente el H96, encendido del monitor, autoinicio tras energía, ni modo kiosco administrado.

La sincronización usa consultas de ~5 s y el panel refresca estados ~8 s; el reporte detallado es ~15 s. “En línea” significa contacto del reproductor, no prueba de imagen física en el monitor. No equivale a sincronización de fotogramas de un videowall.

Inventario Pro 360, OAuth, S3/FTPS/Drive y VPS reales requieren configuración y pruebas de sus cuentas. La integración de catálogo no altera costos, stock, ventas ni fiscal. No se ha modificado SectorPay.
