# Sector Sign · 2 APK desde un solo proyecto Android

Este proyecto genera **dos APK diferentes**, no siete apps:

- `centroDebug`: **Sector Sign Mando** (`com.sectorsign.centro`), abre `/index.php`.
- `receptorDebug`: **Sector Sign Reproductor** (`com.sectorsign.receptor`), abre `/player.php`.

Ambas usan la misma implementación `MainActivity.java` y los mismos recursos. La interfaz, las promociones, las cuentas y la reproducción residen en la **web compartida** instalada en aaPanel. El servidor requiere PHP + MySQL; las APK no requieren Node.js para ejecutarse. Node/Angular y Gradle son solo herramientas de *compilación de fuentes*.

## Compilar APK de evaluación

1. Instala **Android Studio** y descarga Android SDK API 35, Build Tools 35 y JDK 17.
2. Abre la carpeta `android-unificado` como proyecto Gradle.
3. Deja que Android Studio sincronice Gradle usando sus repositorios oficiales. En ausencia de `gradlew`, usa el Gradle integrado o crea el wrapper (8.10.2).
4. En `Build > Select Build Variant` selecciona `centroDebug`, después `Build > Build APK(s)`.
5. Repite con `receptorDebug`.
6. Encontrarás las APK en `app/build/outputs/apk/centro/debug/` y `app/build/outputs/apk/receptor/debug/`.

Un build `Debug` es automáticamente **firmado por Android Gradle Plugin** con un certificado local de evaluación. Puedes instalarlo para hacer pruebas, pero NO es una firma de distribución profesional ni garantiza la continuidad de futuras actualizaciones en otra computadora.

**Para producción**: en Android Studio selecciona `Build > Generate Signed Bundle / APK`, elige una keystore propia bajo tu custodia, respalda la clave y genera un release para cada flavor. No compartas tu keystore o contraseñas en el chat, en ZIP ni en un repositorio.

## Compilar mediante automatización

Hay un workflow listo en `.github/workflows/build-two-android-apps.yml` para un repositorio de GitHub dedicado. Se debe colocar ese workflow en la raíz del repositorio (misma ubicación indicada), mantener `android-unificado` en su carpeta y ejecutarlo desde `Actions`. No hemos subido este proyecto a ninguna cuenta GitHub. El workflow descarga Android SDK/Gradle y publica **ambas APK debug firmadas** como artefactos de esa ejecución.

## Compatibilidad

Android 8+ (API 26), dispositivos con Android WebView compatible. La variante Reproductor intenta permanecer a pantalla completa y encendida cuando está activa; el inicio automático tras un corte de corriente y el encendido remoto de la TV dependen del firmware. El modo sin conexión conserva anuncios cuando el contenido ya fue descargado en una sesión anterior y el motor WebView mantiene su almacenamiento: **debe probarse en el H96 real**.

Para administración en Windows y macOS, abre el centro de mando mediante navegador o instálalo como PWA. Para Android TV, instala la variante Receptor. Para Mac/Windows no se empaquetó `.exe`/`.dmg` porque la PWA utiliza la misma aplicación web sin duplicar código.
