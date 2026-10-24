# Instalar Sector Sign en aaPanel sin Node.js

1. Crea un sitio nuevo, separado de Inventario Pro 360. Usa PHP 8.2 o superior y HTTPS.
2. Crea una base MySQL vacía con usuario propio. Nunca reutilices las credenciales de Inventario Pro.
3. Sube **el contenido de `server/`** a un directorio privado (por ejemplo `/www/wwwroot/sector-sign/`), dejando `private/`, `storage/` y `sql/` fuera de la carpeta pública.
4. En el sitio aaPanel define **Document root** a `/www/wwwroot/sector-sign/public` (ajusta la ruta según tu VPS). No pongas la raíz en `server/`.
5. Habilita `pdo_mysql`, `fileinfo`, `openssl`, `sodium`; para conexiones remotas/extensiones opcionales también `curl`, `ftp` y `zip`. Revisa límites de tamaño de subida si usarás videos largos.
6. Abre `https://carteleria.tudominio.com/install.php` y configura MySQL y la primera cuenta administradora.
7. Entra en `/index.php`, sube una imagen, crea lista y pulsa `Publicar`.
8. En el TV o teléfono abre `https://carteleria.tudominio.com/player.php`, obtén código, vincúlalo desde el panel y confirma descarga.
9. Si quieres usar la app Android, instala **una de las dos variantes APK** compiladas con el mismo proyecto. Al abrirla, ingresa el dominio del paso 6.
10. Haz una copia cifrada de base y multimedia y configura respaldos externos. Confirma restauración antes de producción.

## Rendimiento / VPS con disco pequeño

Para multimedia usa S3 compatible (Amazon S3/Backblaze/otros) y preferentemente CDN/HTTPS. Para copias de seguridad usa Google Drive/FTPS/almacenamiento externo mediante aaPanel o tareas programadas. **No confundas almacenamiento de copia con origen de streaming**. El balanceo real requiere al menos dos instancias y un proxy/capa de almacenamiento compartido; una configuración Nginx por sí sola no crea otro servidor.

## Windows / Mac

Abre `/index.php` desde Chrome/Edge/Safari y usa la función **Instalar app / Añadir al Dock** cuando el navegador la ofrezca. En Android también puede instalarse la PWA del reproductor desde Chrome; no reemplaza las pruebas del APK ni el comportamiento kiosk del dispositivo.

## Actualización 0.4 a 0.5

Esta entrega conserva la API y el esquema de la línea PHP 0.4. Antes de actualizar haz backup de `private/config.php`, `storage` y MySQL. No sobrescribas archivos de datos con paquetes vacíos ni asumas que el ZIP de código es una copia de seguridad. Los cambios 0.5 aquí están principalmente en empaquetado multiplataforma, manifiestos PWA, fuentes Angular y Android; el backend operativo sigue en 0.4.
