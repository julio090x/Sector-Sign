# Cambios 0.8.0 · 24 octubre 2026

## Multimedia y experiencia

- Subida múltiple y drag/drop en la biblioteca y al editar listas, progreso de subida y compatibilidad MP4/WebM.
- Drag de anuncios desde biblioteca a lista; reordenación arrastrando o por ↑/↓; mover/copy desde una lista a otra usando control de revisión optimista.
- Nueva creatividad tipo **intro SVG** Full HD editable (texto/paleta); generada en servidor, no acepta SVG externo de usuarios.
- Enlace a video MP4 HTTPS o stream HLS HTTPS de origen público; disponible **solo online** cuando el navegador/WebView soporte el formato.

## Clientes y grupos

- Administración grupal de los reproductores vinculados: asignar miembros y enviar comandos juntos.
- **Videowalls** 1×1–4×4: modo espejo (mismo contenido), modo panorámico (una imagen dividida); slots ordenados, permisos por organización y reloj común best effort.
- Se eliminan archivos offline al desvincular el reproductor online; no se conservan anuncios de una organización en un equipo desasociado.

## Datos y seguridad

- Migración aditiva de `media.kind` a `stream`, nuevo `media.stream_url`, nuevas tablas `video_walls` y `video_wall_members`.
- Intro con puro SVG (sin `foreignObject`) y escapado XML; URLs streaming HTTPS bloqueando usuarios, localhost e IPs reservadas.
- Exportación offline rechaza streams online, con mensaje 422; las campañas offline antiguas se mantienen en caso de fallo de descarga.
- Se incorpora sincronizador CLI del catálogo comercial público de Inventario Pro, revisiones estables y firmas HMAC. No conecta automáticamente datos reales.

## Alcance restringido

No incluye un servidor de transcodificación, casting de pantalla móvil, captura HDMI, sincronización de fotogramas por hardware, APK 0.8 firmada de producción ni acceso verificado al aaPanel/H96. La traducción completa de todas las secciones es trabajo pendiente.
