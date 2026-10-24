# Cambios 0.7.0

Nueva programación por listas completas, editor y simulador, copia/clonado de franjas, orden de listas, reglas nocturnas, fechas, prioridades y respaldo. Un programa se publica como una foto inmutable de todas sus listas/archivos. Reutiliza el flujo seguro de descarga y confirmación existente.

Nueva supervisión por dispositivo: versiones web/APK, plataforma, resolución, última franja, listas y anuncio reportados, caché/cuota del navegador y avance de descarga por archivos. Estos datos los reporta el reproductor; no certifican que el televisor esté encendido ni que haya una persona mirando.

Base de datos: tablas `programs` y `device_status`; migración aditiva. API: `programs.list`, `programs.save`, `programs.publish`, `player.status`; seguridad de roles, CSRF, organización y bearer del dispositivo. Control de revisión para evitar guardar sobre cambios ajenos.

Reproductor: cambios locales de horario cada segundo, sin esperar nueva publicación; reintento de confirmación perdida; prevención de render tardío de un anuncio anterior; telemetría independiente durante descargas.

Android: compilación real de las dos variantes con AGP 8.7.3, Gradle 8.10.2, JDK 17, Android SDK 35. Selector de imágenes/videos para administrar, configuración adaptable, UA con versión/modelo y rechazo de errores TLS. No se añaden servicios permanentes de Node.

Continúa pendiente la conversión completa a componentes Angular, conexión real del inventario vigente, configuración de proveedores externos, validación prolongada en H96 y despliegue en aaPanel. La APK es contenedor web compartido, no un motor de reproducción nativo independiente.
