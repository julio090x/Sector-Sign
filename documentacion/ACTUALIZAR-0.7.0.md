# Actualización desde 0.6.0 a 0.7.0

1. Haga un respaldo verificable de MySQL, `private/config.php` y todo `storage/`. Conserve también claves OAuth/S3, backup_key y la firma Android. No publique estos archivos.
2. Pruebe primero en una copia con otra base. Mantenga el sitio unos minutos en mantenimiento durante la migración.
3. Sustituya el código del servidor, **sin borrar ni reemplazar `private/config.php` y `storage/`**. Mantenga la misma URL y raíz `public/`.
4. Desde la terminal aaPanel, en la carpeta del servidor: `php tools/upgrade.php` muestra cambios; `php tools/upgrade.php --apply` crea las tablas nuevas `programs` y `device_status`. El script se puede repetir. Alternativa: importe `sql/upgrade-0.6-to-0.7.mysql.sql` en la MISMA base de Sector Sign mediante phpMyAdmin. No importe un SQL de otro sistema.
5. Recargue panel y reproductores. Compruebe `/version.php`, el indicador 0.7.0 y la actualización de la caché del reproductor.
6. Las listas individuales existentes siguen siendo válidas. Los programas nuevos solo sustituyen la campaña al pulsar Publicar.
7. Editar una lista o un programa solo guarda un borrador. Vuelva a publicar el programa para que incluya esas revisiones. Las pantallas mantienen el contenido anterior hasta que descarguen todos los recursos.

## Reversión

No borre las dos tablas para volver al código anterior. Antes de revertir, asigne listas individuales a pantallas: un reproductor viejo no interpreta programas múltiples. Restaure solo una copia coherente de datos y multimedia. Nunca restaure sobre una producción sin copia previa.

## Aplicaciones

VersionCode 7, versión 0.7.0, Android 8+ (API 26). Las APK release suministradas tienen la misma firma del propietario para ambos identificadores: `com.sectorsign.centro` y `com.sectorsign.receptor`. Si instaló una APK debug con otra firma, Android puede exigir desinstalarla; eso puede borrar caché/vinculación. No haga ese cambio en una pantalla activa sin preparar otra reproducción.
