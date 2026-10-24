# Cambios 0.6.0 — 24 octubre 2026

## Implementado y comprobable

- Endpoint `/version.php`: versión del software instalado, fecha de última actualización y notas de cambios.
- Endpoint autenticado `system.release` para detectar si el panel web cargado está desactualizado.
- Centro de Mando: indicador de conexión, versión, aviso de actualización, historial de cambios y última señal por pantalla.
- Refresco de estado cada ~8 segundos en panel, sin reemplazar formularios o editores en curso.
- Reproductor: refresco de publicación/comandos cada ~5 segundos en línea, reintentos hasta 60 segundos cuando no hay conexión, conservación del contenido previamente descargado.
- Optimización de actualizaciones de `last_seen_at` en MySQL a intervalos de 15 segundos.
- Reproductor muestra versión, fecha de actualización, último contacto y campaña actual.
- Android: ambas variantes comparten `versionName=0.6.0` y `versionCode=6` en Gradle.
- Workflow CI para generar dos APK firmadas **debug**, verificar paquete/firma y exportar SHA-256.
- Proyecto Git independiente local con workflow reproducible y guía para publicar en un repositorio nuevo.

## Verificación y límites

- `php -l` y `server/tests/qa.sh`: pasan en el entorno actual.
- `node --check` en JS: pasa.
- `scripts/verificar-versiones.py`: comprueba coincidencia de las versiones.
- `javac` usando stubs Android: pasa; no es una compilación APK con el SDK.
- No se comprobó una instalación MySQL real porque no hay MySQL local ni credenciales del VPS.
- No se generaron APK ni se certificó comportamiento de WebView/H96.
- Sin repositorio remoto independiente todavía: la conexión GitHub disponible muestra solamente el repositorio `sectorpay`. Nunca se mezclaron allí archivos de Sector Sign.
- La comprobación de actualizaciones compara la app abierta con el servidor **instalado**, no con una versión nueva de GitHub Releases.
