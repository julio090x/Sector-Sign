# Estado real y pruebas de Sector Sign 0.5.0

## Ejecutadas aquí

- Se descomprimió el ZIP íntegro 0.4 y se reutilizó el código que ya pasaba sus contratos.
- `bash server/tests/qa.sh`: aprobado (sintaxis PHP/JS, importación pública, horarios, cifrado y rutas GET/POST).
- `tsc -p angular-unificado/tsconfig.compartido.json --noEmit`: aprobado (código TypeScript compartido, no es compilación Angular completa).
- Compilación de la clase Java `MainActivity` contra stubs de tipos Android para comprobar sintaxis: aprobada. Los stubs NO sustituyen el SDK real y no prueban WebView/Gradle.
- Manifiestos PWA y APK XML comprobados como JSON/XML válidos.
- Archivos ZIP validados al final con `unzip -t` y hashes SHA-256.

## No ejecutadas / requieren entorno

- No se compiló ninguna APK ni se firmó con `apksigner` porque Android SDK/Build Tools/Gradle no están instalados aquí y no se pudieron descargar.
- No se ejecutó `npm install` ni `ng build` por falta de acceso al registro npm; no se validó la compilación total Angular.
- No se ejecutaron pruebas reales con MySQL/aaPanel del usuario ni hardware H96.
- No se certificó Google/Microsoft OAuth dentro de WebView. Usar navegador/PWA para autenticación OAuth.
- No hay instalación en el VPS del usuario y el conector no apunta aún a su Inventario Pro 360 real.
- La caché offline dentro de Android WebView debe verificarse tras reinicio, falta de red y dispositivo sin espacio.

## Problemas que resolver antes de producción

1. Compilar y firmar ambas APK con SDK oficial, verificar `apksigner` y ejecutar instalación limpia y actualización.
2. Comprobar login por correo y contraseña, vinculación, publicación, reproducción, caché offline, contenido grande y cambios de orientación.
3. Para Angular, ejecutar `npm ci`/`npm install`, `ng build` y pruebas funcionales reales antes de sustituir las páginas PHP existentes.
4. Si se usa firma release estable, conservar su keystore fuera del repositorio y hacer restauración de prueba.
5. Monitorizar MySQL, tiempos de descarga, reintentos, uso de espacio y estado de cada pantalla.
