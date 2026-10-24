# Sector Sign 0.8.0 · Plataforma de cartelería digital

Dos aplicaciones compartiendo PHP/MySQL en aaPanel: **Centro de Mando** (web/Android) y **Reproductor** (TV Stick, Android, navegador/PWA). No requiere Node.js, Composer ni Docker en el servidor. El workspace Angular/TypeScript es de migración parcial; la interfaz web PHP/JavaScript sigue siendo la que está operativa.

## Novedades de la 0.8.0

- Videos MP4/WebM e imágenes PNG/JPEG/WebP/GIF; subida múltiple arrastrando al panel, progreso visible y biblioteca reutilizable.
- Mover/copy anuncios entre listas con revisiones, ordenar mediante arrastre o botones.
- Grupos de pantallas independientes por local y conjunto (ej.: Primer piso, Pasillo 1, cuatro pantallas), comandos remotos conjuntos.
- Videowall/mosaico espejo o panorámico de hasta 16 reproductores (4×4), best-effort sin hardware sync.
- Intros SVG Full HD editables y enlaces HTTPS a directo MP4/HLS con advertencia de compatibilidad/offline.
- Reproductor offline con verificación SHA-256 de archivos descargados y revocación de contenido al desvincular.
- Integración con Inventario Pro 360 via exportador de catálogo **público** de solo lectura, firmado HMAC, sin leer datos privados ni alterar inventario.
- ES/EN/PT en la navegación principal, interfaz responsive y versión mostrada en el panel/reproductor.

## Código

`server/` aplicación PHP 8.2+, MySQL, CSS, JS y PWA. `android-unificado/` dos flavours Android con WebView compartido. `angular-unificado/` proyecto Angular en migración **no completa**. `integracion-inventario-pro-360/` firmador y sincronizador comercial. `documentacion/` manuales y QA.

## Comprobar antes de instalar

```bash
sh server/tests/qa.sh
php integracion-inventario-pro-360/tests/contract.php
python scripts/verificar-versiones.py
```

**Entrega preparada para piloto, no certificada de producción.** El usuario debe configurar aaPanel, S3/FTP o Google Drive, OAuth, exportador de Inventario Pro y probar el equipo H96. Consulte `LEEME-PRIMERO.md`.
