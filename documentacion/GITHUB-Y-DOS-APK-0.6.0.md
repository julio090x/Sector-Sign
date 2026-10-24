# GitHub independiente · Sector Sign 0.6.0

**Repositorio objetivo:** `sector-sign` **privado**, separado de `sectorpay` y de Inventario Pro 360. No se ha creado en GitHub todavía; la acción de creación no está disponible en el conector actual.**

## Subir el código desde un PC

1. Crea un repositorio **privado y vacío** llamado `sector-sign` en GitHub (sin README ni .gitignore inicial).
2. Descomprime el ZIP `SectorSign-03-RepositorioCompleto-0.6.0.zip`.
3. En una terminal situada en la carpeta del proyecto ejecuta:

   ```bash
   git init -b main
   git add .
   git commit -m "Sector Sign 0.6.0: dos apps, sincronización y versiones"
   git remote add origin https://github.com/TU_USUARIO/sector-sign.git
   git push -u origin main
   ```

   Si vas a usar el **bundle Git** suministrado, basta `git clone SectorSign-0.6.0.bundle sector-sign`, entrar a la carpeta y ejecutar `bash scripts/subir-github.sh URL_DEL_REPOSITORIO`.

4. En la pestaña **Actions** comprueba el workflow `Sector Sign — compilar dos APK Android` (se ejecuta al subir el proyecto o se puede ejecutar manualmente).
5. Descarga por separado los artefactos `SectorSign-Mando-APK-debug-0.6.0` y `SectorSign-Reproductor-APK-debug-0.6.0`; cada ZIP contendrá una APK firmada de evaluación.

## Si deseas firma de producción

Genera tu propia clave de firma en Android Studio y consérvala fuera del repositorio. Las APK debug usan certificados locales automáticos de prueba; no son firma de producción permanente. Firma una versión de distribución en Android Studio o configura una acción CI con secretos cifrados, de forma controlada.

## Versión / últimas actualizaciones

- `server/private/release.json`: versión del servidor y notas de cambios.
- `android-unificado/app/build.gradle`: `versionName 0.6.0`, `versionCode 6`.
- `angular-unificado/package.json`: versión de interfaz.
- Pantalla de Mando: ficha de versión en encabezado y Ajustes.
- Pantalla de Reproductor: Configuración → versión, último contacto y campaña aplicada.
- Script `scripts/verificar-versiones.py`: comprueba coincidencias antes de compilar.

**No reemplazar los archivos `server/private/config.php` ni `server/storage/` al actualizar**. Haz copia de la base y multimedia antes de subir código a producción.
