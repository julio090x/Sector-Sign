# Compilar y firmar las dos aplicaciones — 0.7.0

Las dos APK ya fueron compiladas en GitHub Actions:
https://github.com/julio090x/Sector-Sign/actions/runs/40092956507

El repositorio es https://github.com/julio090x/Sector-Sign. Contiene un proyecto Android compartido con dos variantes, no dos proyectos duplicados. El frontend y el reproductor son web compartida alojada en su servidor. Las APK son contenedores WebView ligeros; no incluyen una copia completa del servidor PHP ni la base MySQL.

## Productos

| Variante | ID | Entrada web |
|---|---|---|
| centro | com.sectorsign.centro | /index.php |
| receptor | com.sectorsign.receptor | /player.php |

VersionName: 0.7.0; versionCode: 7; Android mínimo 8.0 (API 26). Compile/target SDK 35. JDK 17, Gradle 8.10.2, plugin Android 8.7.3.

## Compilar

Abra `android-unificado` en Android Studio con SDK 35 y Build Tools 35.0.0, o ejecute desde esa carpeta:

```bash
gradle --no-daemon :app:assembleCentroRelease :app:assembleReceptorRelease
```

El workflow raíz `.github/workflows/build-two-android-apps.yml` compila además variantes Debug firmadas para evaluación. En GitHub, Actions → Sector Sign - Android APK → Run workflow. Los artefactos contienen `unsigned/` (Release sin firmar), `evaluation/` (Debug) y herramientas de verificación. Una APK Debug no puede sustituir una Release firmada con otra clave sin desinstalar.

## Firma para mantener actualizaciones

Las APK entregadas `SectorSign-Mando-0.7.0.apk` y `SectorSign-Reproductor-0.7.0.apk` se firmaron con una clave Release exclusiva, fuera de GitHub, y pasaron verificación oficial apksigner v2/v3.

Certificado SHA-256 público: `21b49a67e68cfa634453eac258e79f8fc243695fe729b6506ae564271d8d8457`.

El titular recibe por separado el respaldo privado de firma. Alias: `sector-sign`. Guarde ese respaldo cifrado, fuera de GitHub y del directorio público web. Contiene secretos y NO debe redistribuirse. No es necesario para instalar las APK.

Para futuras actualizaciones, conserve el mismo ID y clave e incremente versionCode. Firme cada Release con Android Studio → Generate Signed Bundle / APK usando su JKS, o con `apksigner` del SDK. No genere una clave diferente para cada versión. No introduzca contraseñas en el código, argumentos de shell, logs o repositorio.

## Conexión y límites

Instale el servidor, configure HTTPS y escriba su misma dirección en ambas apps. Mando utiliza correo/contraseña; Reproductor muestra un código para vincular desde Pantallas. El acceso social desde el contenedor no está habilitado: la navegación se limita al origen autorizado. Google/Microsoft desde la web requieren configuración externa.

El reproductor conserva contenido descargado y programa local cuando WebView mantiene su almacenamiento. HTTPS es necesario para Service Worker; HTTP de IP privada es solo conectividad LAN, no garantía de caché offline. No se probó arranque en frío ni reproducción prolongada en el H96 real, ni encendido físico de TV. No incluye arranque automático tras reiniciar Android.

Windows y macOS usan el panel web/PWA. No se generaron ejecutables .exe/.dmg. La migración completa a componentes Angular sigue pendiente; se reutiliza la interfaz funcional PHP/JavaScript.
