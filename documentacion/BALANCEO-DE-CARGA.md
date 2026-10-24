# Balanceo y rendimiento · Preparado, no activado

Para **un VPS con poco tráfico**, usa un solo PHP-FPM + MySQL y distribuye los anuncios por S3/CDN. **El balanceo entre servidores no acelera un solo VPS**; hace falta un segundo servidor real y un balanceador o CDN delante.

## Arquitectura para crecer

```
          Usuarios y reproductores
                    |
                HTTPS / CDN
                    |
          Nginx / LB (con health)
             /               \
       PHP-FPM A           PHP-FPM B
          |                    |
          +------ MySQL -------+
          |                    |
        S3/CDN <--- multimedia compartida
```

En `private/config.php` configura `trusted_proxy_ips` con la IP privada exacta del balanceador si necesitas conservar el IP original de visitantes para protección de acceso; nunca confíes en cabeceras X-Forwarded-For de visitantes arbitrarios.

En ambos nodos usa el **mismo código**, `private/config.php` con los **mismos secretos** y credenciales, y una única base MySQL. La tabla `web_sessions` almacena las sesiones en MySQL; no se requiere afinidad por cookie. **El almacenamiento de multimedia debe estar en S3/FTPS/CDN**, no en carpetas locales independientes. Las capturas de contenido se guardan en MySQL para poder verlas desde cualquier nodo.

**Advertencia:** la tabla de pantallas se consulta cada 20 segundos por reproductor; cuando superes cientos de pantallas revisa CPU, MySQL Slow Query Log, conexiones PHP-FPM, caché OPcache y consultas. Para escalar a miles con telemetría en tiempo real se recomienda Redis/colas para trabajos asíncronos, pero esta edición **no lo exige** para funcionar.

## Ejemplo de Nginx upstream (solo en un balanceador separado)

```nginx
upstream sector_sign_backend {
    least_conn;
    server 10.0.0.11:80 max_fails=3 fail_timeout=30s;
    server 10.0.0.12:80 max_fails=3 fail_timeout=30s;
    keepalive 32;
}
server {
    listen 443 ssl http2;
    server_name sign.ejemplo.com;
    # Gestiona certificados SSL en este servidor aparte.
    location / {
        proxy_pass http://sector_sign_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_read_timeout 180s;
        client_max_body_size 160m;
    }
}
```

**No pegues ese bloque en el Nginx activo de aaPanel sin revisarlo.** IP 10.0.0.11/12 y dominio son ejemplos; para producción se necesita HTTPS entre LB y/o red privada, cortafuegos, comprobaciones de salud, monitorización y no publicar MySQL al mundo.

## CDN / rendimiento

- Activar OPcache para PHP.
- Biblioteca de anuncios con S3/CDN y cabeceras de caché (los anuncios son identificados por ID/versión inmutable).
- JPG/WebP en vez de PNG enorme donde no importe transparencia; videos H.264/AAC MP4 para mejor compatibilidad con TV/Android.
- Dividir promociones pesadas en varias listas y evitar videos de 500 MB por pantalla.
- Los reproductores descargan antes de reproducir y reutilizan los blobs almacenados localmente.
- Ejecutar `tools/cleanup.php` diariamente para eliminar sesiones viejas y archivos archivados sin referencias.

No se ha ejecutado una prueba de carga real con cientos de pantallas en tu servidor.
