# Sector Sign 0.8.0 · Evidencia y límites de QA

**Validado en esta sesión:**

- `bash server/tests/qa.sh`: sintaxis PHP completa, validación de tipos, generación SVG escapada, cifrado y manipulación de backups, validación de 3 listas/horarios 16 casos, resolución offline de programación 32 casos, seguridad HTTP GET/POST y página de instalación.
- `php server/tests/wall-creatives.php`: reglas de grupos/matrices, slots únicos, rechazo de duplicados, URL HTTPS y bloqueo de direcciones locales/privadas, intros SVG puras.
- `php integracion-inventario-pro-360/tests/contract.php`: solo datos públicos, revisión estable y HMAC del editor.
- `python scripts/verificar-versiones.py`: coherencia de 0.8.0 en servidor, Android, Angular, PWA y CI.
- `node --check` en archivos JS; no requiere Node para la ejecución del servidor.
- ZIP limpio y validación de integridad se guardan en archivo SHA256 de entrega.

**No validado para afirmar en producción:**

- MySQL 8/MariaDB reales con migración 0.8 en el VPS del usuario; este contenedor no tiene MySQL server ni `pdo_mysql`. Las pruebas de GitHub MySQL correspondientes a 0.7 ya habían pasado, pero **no validan 0.8**.
- Navegación visual real de 0.8: Chromium de esta sesión bloqueó incluso `localhost` y `https` interceptado con `ERR_BLOCKED_BY_ADMINISTRATOR`; no se fabricaron capturas.
- APK 0.8.0: no compilaron ni firmaron aquí; requieren SDK/CI y clave de firma del usuario para actualizar sin reinstalar.
- HLS en Android TV/H96, sincronización exacta de videowall, HDMI activo, reproducción continuada tras cortes/reinicio eléctrico, OAuth, Amazon S3/FTPS/Google Drive del usuario.
- Integración efectiva con Inventario Pro 360 (no hay binario vigente verificado).

**Precauciones:** revisa la migración con backup antes de aplicar, prueba un reproductor piloto antes de repartir a clientes y conserva la misma keystore Android fuera de cualquier repositorio público.
