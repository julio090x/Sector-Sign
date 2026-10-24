# Sector Sign 0.8.0 — Comienza aquí

**Dos aplicaciones: Centro de Mando y Reproductor.** Un único servidor PHP/MySQL en aaPanel, sin Node.js en producción. Android, Windows, macOS y tablet usan interfaces web/PWA compartidas; Angular sigue en migración parcial.

## Instalar nueva instalación en aaPanel

1. Crea un sitio nuevo en aaPanel para Sector Sign y una **base MySQL/MariaDB vacía e independiente**. No sobreescribas Inventario Pro 360 ni su almacenamiento.
2. PHP 8.2+ con `pdo_mysql`, `fileinfo`, `openssl`, `sodium`. Activa `curl`, `zip`, `ftp` según las funciones. Ajusta `upload_max_filesize` y `post_max_size` a más de 150 MB solo si tu VPS dispone de capacidad.
3. Extrae el ZIP servidor en una carpeta nueva. Apunta la raíz web del dominio a `public/` (no a la raíz del ZIP), con **HTTPS activo**.
4. Abre `https://TU-SUBDOMINIO/install.php` y usa la clave del archivo privado `private/install.key` junto con MySQL y el primer administrador.
5. Entra a Centro de Mando. En **Contenido**, sube fotos o videos arrastrándolos; también puedes crear una intro estática y registrar una transmisión HTTPS.
6. En **Locales** crea, por ejemplo, `Tienda principal`. En **Grupos**, crea `Primer piso · Pasillo 1`. Abre el Reproductor en cada equipo, vincúlalo mediante su código y asígnalo al local/grupo.
7. En **Listas**, crea el carrusel y configura cada anuncio. Si debes reutilizar anuncios, usa **Mover/copiar anuncio**. En **Programación**, establece horarios y repite listas. Publica a las pantallas seleccionadas.
8. Si tienes cuatro pantallas, entra a **Videowalls** para configurar `2 filas × 2 columnas`: `Espejo` muestra lo mismo en las cuatro; `Panorámico` divide una imagen en cuatro. En ambos casos **asigna la misma publicación** a las cuatro pantallas.

## Actualizar desde 0.7.0

- **Haz copia completa** de MySQL, los archivos `storage`, `private` y multimedia/objetos antes de actualizar.
- Realiza primero la actualización en un **subdominio de pruebas**; coloca el código nuevo sin sobrescribir `private/config.php`, directorios de uploads ni respaldos.
- Ejecuta `php tools/upgrade.php` en CLI para **ver las migraciones**. Si son correctas, ejecuta `php tools/upgrade.php --apply` una sola vez (el script verifica qué objetos faltan). No importes una base vacía sobre tu base productiva.
- Comprueba `/version.php` (0.8.0) y verifica que cada pantalla haya aplicado su campaña. El service worker del Reproductor cambia a `v080`, manteniendo el almacenamiento IndexedDB.
- Las APK `0.7.0` anteriores siguen abriendo el servidor actualizado porque cargan la interfaz web; los cambios del código Android 0.8.0 exigen compilar una APK nueva y firmarla con la **misma clave** si quieres actualizar la app instalada sin reinstalar.

## Inventario Pro 360

Crea la integración desde **Inventario** en el panel. El ZIP de conector incluye `bin/enviar-catalogo-publico.php`, que lee JSON comercial autorizado y envía nombre, presentación, precio público, promociones y revisión mediante HMAC. **No hay conexión automática real hasta adaptar el exportador a tu ZIP vigente de Inventario Pro**. Nunca se envían costos, stock privado, compras, ventas ni documentos fiscales.

## Límites y pruebas

- Los directos `.mp4`/`.m3u8` requieren conexión; HLS depende del reproductor/navegador y no se almacena para uso offline. No es casting de pantalla ni un servidor para generar transmisiones.
- Videowall basado en WebView/browser y reloj servidor: **sin sincronización profesional de fotogramas**. Para un mural preciso usa controladores/videowall hardware y mide latencia de cada TV Stick.
- Se verificaron sintaxis, unidades y contratos del código; **no se ha instalado 0.8 en tu aaPanel ni probado el H96 real**. No hay APK 0.8.0 compilada en esta entrega, solo el código listo para compilar; las anteriores 0.7 siguen siendo instalables para el piloto.
- El navegador de pruebas de esta sesión bloqueó acceso a localhost incluso con rutas interceptadas; no se declara una prueba visual automática real de 0.8.

Consulta `documentacion/CAMBIOS-0.8.0.md`, `ACTUALIZAR-0.7-A-0.8.md` y `REPORTE-QA-0.8.0.md` para detalles.
