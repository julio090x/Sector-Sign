# Actualización controlada Sector Sign 0.7 → 0.8

1. Haz respaldos verificables de MySQL y de `storage/` y `private/` y todo almacenamiento multimedia. Mantén una copia externa.
2. Despliega primero en staging con una copia **aislada** de los datos, jamás directamente sobre tus anuncios reales.
3. Extrae **Servidor-aaPanel-0.8.0.zip** en un directorio nuevo. Configura la raíz pública a `/public`. Copia la configuración privada **desde tu instalación existente** a su ruta correspondiente, solo en staging; nunca la empaquetes con el código.
4. En PHP CLI ejecuta `php tools/upgrade.php` (simulación). Comprueba que solo añade `media.stream_url`, amplía `media.kind` y crea `video_walls` y `video_wall_members`, además de cualquier corrección de migraciones anteriores pendiente.
5. Aplica `php tools/upgrade.php --apply`. Comprueba `/version.php`, inicio de sesión, biblioteca y listas existentes, grupo y videowall de prueba.
6. Publica una lista nueva en un reproductor **de prueba**. Comprueba descarga completa, reproducción offline y vuelta a internet. Haz una prueba de eliminación al desvincular.
7. Conserva la base productiva original para rollback; si algo falla vuelve a 0.7 y restaura el backup coherente de SQL, private y media.

Si tienes la APK release 0.7 firmada con tu clave privada, **no la sobrescribas con una APK debug o con una firma distinta**. Una actualización Android compatible exige la misma identidad de firma. Las APK 0.7 cargan la nueva interfaz desde el servidor hasta que distribuyas 0.8 con la firma correcta.
