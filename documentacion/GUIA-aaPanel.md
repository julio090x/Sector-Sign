# Sector Sign 0.7.0 · Instalación simple en aaPanel (PHP + MySQL)

**No necesita Node.js, Composer, Docker ni bases de datos externas para comenzar.** Necesita un VPS con aaPanel, dominio y SSL, PHP 8.2 o superior, MySQL 8.0/MariaDB 10.5+, y una base de datos vacía.

## Instalación rápida (15 pasos sin comandos complejos)

1. En aaPanel, **App Store**, instala PHP 8.2/8.3, MySQL/MariaDB y Nginx o Apache. No cambies una instalación en uso.
2. Habilita estas extensiones PHP en aaPanel: **pdo_mysql, fileinfo, openssl, sodium**. Para almacenamiento remoto o integraciones: **curl** y **ftp**. Para exportación de listas ZIP y respaldo cifrado: **zip**. `mbstring` es recomendable pero no obligatorio.
3. Ve a **Website → PHP Project → Add site**. Crea un subdominio para la cartelería, con SSL. **No uses el dominio ni la carpeta actual de Inventario Pro 360.**
4. Crea una base MySQL vacía y un usuario con permisos solo sobre esa base desde la opción Database de aaPanel. Guarda host, nombre, usuario y contraseña.
5. Sube el archivo `SectorSign-Servidor-aaPanel-0.7.0.zip` a una carpeta nueva, por ejemplo `/www/wwwroot/sign.ejemplo.com/`. Extrae el ZIP allí. La estructura contiene `public`, `private`, `storage`, `sql`, `tools` y `tests`.
6. Modifica el **Document Root** del sitio en aaPanel para que apunte **únicamente a** `/www/wwwroot/sign.ejemplo.com/public`. **Nunca apuntes al directorio padre**, que contiene configuraciones y copias de seguridad.
7. Activa HTTPS y fuerza redirección HTTP→HTTPS. Si usas Nginx, aaPanel debe tener PHP habilitado para el sitio. No pegues una configuración genérica que borre tu configuración actual.
8. Comprueba que el usuario PHP del sitio (`www` en muchas instalaciones de aaPanel) pueda escribir en `private` durante la instalación y `storage` durante el funcionamiento. Configura permisos de forma restringida; **no uses 777**.
9. Si está habilitado *open_basedir / Anti-XSS site protection*, permite el directorio completo del proyecto para que PHP alcance `private`, `sql`, `storage`, sin hacerlos públicos.
10. Abre `https://TU_DOMINIO/install.php`.
11. Desde el **Administrador de archivos de aaPanel**, abre `private/install.key` y copia su código privado al asistente. Este paso impide que un extraño cree la primera cuenta si visita antes el sitio. **No publiques el código.**
12. Escribe los datos de la base MySQL vacía, la URL HTTPS, el nombre del negocio, correo y contraseña de administrador de al menos 12 caracteres.
13. Pulsa **Instalar de forma segura**. Se crearán las tablas y `private/config.php`; el código de instalación se eliminará. Haz una copia segura de `backup_key` desde ese archivo porque permite recuperar los respaldos cifrados.
14. Entra con tu correo y contraseña. Abre **Contenido** para subir anuncios; **Listas** para ordenarlos; **Pantallas → Vincular** para asignar el código generado por el reproductor.
15. En otra pestaña o dispositivo abre `https://TU_DOMINIO/player.php`; copia su código de 8 cifras al centro de mando. Guarda la lista y pulsa **Publicar**.

## Ajustes recomendados para videos

En el PHP del sitio configura `upload_max_filesize=150M`, `post_max_size=160M`, `max_execution_time=180`, `memory_limit=256M` y el `client_max_body_size` equivalente (160m) en Nginx. **Si tu VPS tiene poco disco, los videos se recibirán temporalmente en PHP antes de enviarse a S3/FTPS, por lo que necesitas al menos el tamaño de cada archivo libre en el VPS durante la subida.**

## Uso sin internet

- **Reproductor conectado antes del corte:** descarga todas las imágenes/videos en IndexedDB, verifica tamaño y SHA-256 cuando WebCrypto está disponible y activa el manifiesto al completar. Durante un corte reproduce la última campaña que estaba descargada.
- **Con el navegador instalado como PWA:** el Service Worker también guarda el shell (`player.php` y JS/CSS), pero su instalación requiere HTTPS o `localhost`; en Android WebView local HTTP puede conservar IndexedDB, pero el shell offline puede depender de la caché de WebView.
- **HTML / ZIP offline:** en el editor de listas descarga los anuncios para abrirlos fuera del servidor. Los navegadores de ciertos televisores no admiten HTML desde USB o algunos códecs; pruébalos primero.
- **Control local sin internet WAN:** instala el mismo servidor aaPanel/PHP/MySQL **dentro de tu red** o monta una réplica local específica. No basta con que el móvil esté en el mismo Wi-Fi si el servidor principal está fuera y no tienes Internet.

## Google / Microsoft

Después de instalar, registra **tu propia aplicación** con Google Cloud y Microsoft Entra. Configura cliente y secreto solo en `private/config.php` (en la sección `oauth`), no los escribas en JavaScript.

Redirección exacta de Google: `https://TU_DOMINIO/oauth.php?provider=google`.

Redirección exacta de Microsoft: `https://TU_DOMINIO/oauth.php?provider=microsoft`; admite cuentas personales en el registro de aplicación adecuado. **Google y Microsoft requieren vinculación explícita desde Ajustes** después de entrar con correo/contraseña en Sector Sign; no se vinculan cuentas automáticamente por coincidencia de correo.

## Respaldos: aaPanel

En aaPanel **Cron → Backup Database**, programa una copia diaria de la base MySQL de Sector Sign. Luego añade **Backup Website** de la carpeta del proyecto completa (media, private, tools, código). En ambos selecciona un destino externo **Google Drive, FTP Storage o AWS S3** desde las opciones oficiales del panel; instala y configura la aplicación de almacenamiento si te la solicita. Activa una retención razonable (ej. 14-30 días). **Verifica recuperación en un servidor separado**, no solo que aparezca “completado”.

Opcional: `php tools/backup.php` genera una copia interna cifrada con sodium. `php tools/backup.php --ftps` o `--s3` también la envía si configuraste las credenciales en `private/config.php`. `php tools/backup.php --verify=/ruta/archivo.ssbackup` verifica autenticidad; `--extract=/ruta/archivo.ssbackup --out=/carpeta-vacia` extrae **sin importar automáticamente MySQL**.

## Actualizaciones y seguridad

- Antes de actualizar, haz respaldo y comprueba que existe la clave de recuperación.
- **No sobrescribas** `private/config.php` ni `storage/`; actualiza código en una copia y compara SQL si cambió de versión.
- Mantén los certificados HTTPS y extensiones PHP actualizados.
- No uses claves de MySQL con acceso root, no publiques `storage` ni `private`.
- Si una función falla, revisa **Website → Log** de aaPanel y el log PHP, sin publicar tokens.
- Programa limpieza de sesiones/transitorios (ejemplo: `php /www/wwwroot/sign.ejemplo.com/tools/cleanup.php`) desde Cron, una vez al día.

Documentación oficial de aaPanel: https://www.aapanel.com/docs/Function/php.html · https://www.aapanel.com/docs/Function/Cron.html

## Diagnóstico, contraseñas y actualización 0.4

- El centro de mando incluye **Ajustes → Diagnóstico** para PHP, MySQL, HTTPS y espacio.
- Para recuperación, el administrador crea un enlace de restablecimiento de 60 minutos que entrega manualmente por un canal privado. Una recuperación invalida las sesiones previas.
- En Ajustes puedes introducir el cliente de Google/Microsoft para activar OAuth; **no compartas sus secretos por chat**.
- Si ya usabas 0.3, sigue `ACTUALIZAR-DESDE-0.3.0.md`: migración MySQL antes del código nuevo y sin sobrescribir storage/config.
- Para una prueba SQL integral, crea instalación y base aisladas con nombre terminado `_qa` y ejecuta `php tests/mysql-smoke.php --run`.

## Prueba inmediata sin VPS

Descarga `sector-sign-demo-interactiva-0.4.0.zip`, extrae el HTML y pruébalo en el navegador. **Es una demo simulada local, no se vincula a pantallas reales.**
