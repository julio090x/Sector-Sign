# Compilar las dos APK de Sector Sign

No hace falta crear otro proyecto por cada app. Abre `android-unificado` en Android Studio:

- `centroDebug` → **Sector Sign Mando** (administración desde móvil/tablet).
- `receptorDebug` → **Sector Sign Reproductor** (Android TV, stick, tablet o móvil).

En `Build > Select Build Variant` elige cada variante y pulsa `Build > Build APK(s)`. Se generan dos archivos separados y **firmados con la clave debug local**. También puedes usar el workflow de GitHub Actions incluido en la carpeta Android si alojas las fuentes en un repositorio dedicado, previa conexión voluntaria de tu cuenta.

En Android Studio las variantes comparten `MainActivity.java`, permisos mínimos, validación del servidor y recursos. El flujo web es el mismo de tu aaPanel: `/index.php` y `/player.php`. Si modificas el frontend PHP/JS, actualizas el servidor y ambas apps reciben cambios sin recompilar, salvo modificaciones del contenedor nativo.

**Una APK Debug firmada es para pruebas**, no para distribución profesional. Para firmar versiones estables genera y conserva una clave de firma release que sea tuya. No compartas contraseñas ni el `.jks`.

### Limitación actual

No se entregan dos archivos `.apk` ya compilados porque este entorno no dispone de SDK Android ni descarga de paquetes. Se entregan las fuentes verificadas y el flujo automatizado para construirlos. Ningún archivo ZIP debe renombrarse `.apk`: no sería instalable.
