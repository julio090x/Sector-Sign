# Empieza aquí — Sector Sign 0.7.0

**Solo dos aplicaciones:** Mando y Reproductor. El ZIP del servidor es la instalación común; el ZIP de fuentes es para modificar el proyecto.

## Instalación nueva en aaPanel

1. Cree un sitio PHP 8.2+ nuevo y una base MySQL 8 / MariaDB 10.5+ vacía. No use la base de Inventario Pro.
2. Active `pdo_mysql`, `fileinfo`, `openssl`, `sodium`. Para funciones opcionales, `curl`, `ftp` y `zip`; `mbstring` recomendable.
3. Extraiga el paquete servidor. Configure la raíz pública a `public/` (o `server/public/` si usa el repositorio completo). Active HTTPS.
4. Abra `/install.php`; desde el administrador de archivos lea `private/install.key` y copie ese código en el asistente, junto con los datos MySQL y su nueva cuenta. No comparta contraseñas en el chat.
5. Inicie sesión. Suba imágenes/videos en Contenido y cree listas en Listas.
6. Instale **SectorSign-Mando-0.7.0.apk** en su Android para administrar. En PC/Mac use el mismo panel web. Instale **SectorSign-Reproductor-0.7.0.apk** en el equipo conectado a la pantalla.
7. Introduzca el MISMO dominio HTTPS, sin subcarpetas, en las dos apps. El reproductor muestra código; agréguelo en Pantallas → Vincular pantalla. Asigne nombre, local y grupo.
8. En Programación cree franjas, seleccione listas y el orden. Guarde el borrador y luego Publique en los dispositivos. Verifique publicación recibida, no solo “En línea”.

## Ejemplo de programación

Lunes–viernes 08:00–12:00: lista Desayunos. Lunes–viernes 12:00–20:00: Desayunos → Ofertas → Mayorista, en bucle. Sábado–domingo 08:00–22:00: Ofertas → Mayorista. Lista de respaldo: información del negocio.

Una franja con prioridad más alta desplaza las de menor prioridad. A igual prioridad las franjas se concatenan en el orden guardado y no duplican la misma lista. 00:00–00:00 significa día completo. 22:00–02:00 pertenece al día de inicio y cruza medianoche. Los límites de fecha también pertenecen a ese día de inicio.

## Antes de depender del sistema

Ensaye con una pantalla: publique tres listas, verifique recepción, cambie de franja, desconecte internet manteniendo energía y compruebe reproducción. Luego reinicie el equipo sin red y compruebe recuperación. No borre datos/caché del reproductor. Mantenga fecha/hora correctas y suficiente espacio.

El paquete no se instala automáticamente en su VPS. OAuth Google/Microsoft requieren sus credenciales de proveedor y no se promete acceso OAuth dentro del WebView; use correo/contraseña en la APK. El acceso por Google/Microsoft se configura/prueba en navegador.

Conserve la clave de firma fuera de GitHub para futuras actualizaciones. Lea la guía de actualización si ya instaló 0.6.
