# Respaldos externos y almacenamiento multimedia

## Dos cosas diferentes

1. **Backups del negocio:** base de datos y archivos de la aplicación. Destinos recomendados: Google Drive, FTPS o Amazon S3 configurados en **aaPanel Cron**; su sistema de backup de MySQL y Website es independiente de Sector Sign. Guarda por lo menos una copia **fuera** del VPS.
2. **Anuncios en vivo:** JPG/PNG/WebP/GIF/MP4/WebM y banners SVG generados por la integración. Para muchos reproductores se recomiendan S3 con URLs firmadas o CDN, o un servidor FTPS que **también tenga un sitio HTTPS público** para servir los anuncios. Google Drive normal **no es un CDN fiable para videos publicitarios** y puede tener límites, cambios de enlaces y problemas de autorización. Úsalo para copias, no para origin de pantallas.

## Almacenamiento por proveedor

- **Local (por defecto):** sencillo, sin cuentas extra. Adecuado para empezar con 1-3 pantallas, pero consume disco y ancho de banda de tu VPS. No lo uses con balanceo multi-nodo sin disco compartido.
- **S3 / compatible:** en Ajustes → Almacenamiento introduce `endpoint` HTTPS, `bucket`, `region`, `access_key`, `secret_key`; opcional `cdn_url`. Los nuevos archivos se envían al bucket. Configura **CORS** para permitir GET desde el dominio de tu panel/player cuando haya redirección a S3.
- **FTPS + servidor web externo:** FTP con TLS autenticado para subir. Usa una cuenta aislada de permisos limitados. El directorio remoto DEBE tener una URL HTTPS pública que entregue exactamente las rutas `sector-sign/media/<archivo>`. El reproductor obtiene el archivo por HTTPS, no por FTP. No se envían credenciales al dispositivo.

**Importante:** no cambies de proveedor con archivos remotos existentes sin migración verificada. El sistema bloquea cambios que dejarían anuncios huérfanos. Las credenciales se guardan solo fuera de `public` en `private/config.php`.

Ejemplo de CORS S3 (cambia a tu dominio real):

```json
[{"AllowedHeaders":["*"],"AllowedMethods":["GET","HEAD"],"AllowedOrigins":["https://sign.ejemplo.com"],"ExposeHeaders":["ETag","Content-Length"],"MaxAgeSeconds":3600}]
```

No hagas público todo el bucket de backups. **Los archivos multimedia y respaldos deben estar en prefijos/buckets separados** y con distintas credenciales y permisos.

## Backup automático por aaPanel

aaPanel documenta Cron con **Backup Website** y **Backup Database**, destinos Local Disk, FTP Storage, Google Drive, Google Cloud Storage y AWS S3 (dependen de complementos). Programa base y archivos a diario con retención y prueba de restauración. Los respaldos nativos de aaPanel pueden no coincidir temporalmente al segundo entre base y archivos; para una foto transaccional consistente, usa ventanas de baja actividad y el respaldo cifrado de Sector Sign en una operación controlada.

## Recuperación cifrada

`php tools/backup.php`: crea ZIP temporal con SQL y archivos, cifra en bloques autenticados con `backup_key` y lo deja como `.ssbackup`. Si usas la opción `--s3`, se envía con firma AWS SigV4; para `--ftps`, con FTPS. El modo `--verify` desencripta temporalmente y comprueba integridad de ZIP; `--extract` SOLO extrae a una carpeta vacía. Para restaurar, crea otra base MySQL, importa `database.sql`, revisa `private/config.php` y copia multimedia desde el respaldo. **No extraigas encima de la instalación activa.**

Requisito de espacio: la creación de un ZIP y su cifrado ocupan temporalmente más disco que los archivos originales; para VPS pequeños deja que aaPanel realice backups directos hacia almacenamiento externo o utiliza volúmenes de mayor capacidad. Una copia remota no sustituye probar su restauración.
