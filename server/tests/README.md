# Verificar Sector Sign

## Código y funcionalidad básica — en una copia NO instalada

```bash
sh tests/qa.sh
```

Valida todos los PHP, pruebas de medios, programación y cifrado. Si Node está instalado en la computadora de **pruebas**, valida JS y calendario. Si `curl` está disponible, inicia PHP embebido en localhost contra un servidor sin instalar y comprueba que GET no invoque operaciones restringidas. El servidor de pruebas no necesita Node en producción.

## Diagnóstico en aaPanel

```bash
php tools/doctor.php
```

Muestra PHP/extensiones, escritura, MySQL, migración y HTTPS sin imprimir contraseñas. Las extensiones remotas opcionales aparecen con avisos.

## Prueba REAL de MySQL — solamente en una base aislada terminada en `_qa`

Después de instalar una copia de Sector Sign en una base nueva, p. ej. `sector_sign_qa`:

```bash
php tests/mysql-smoke.php --run
```

Rechaza otros nombres de base y emplea `BEGIN/ROLLBACK` para no persistir usuarios ni registros ficticios. Comprueba tablas, escritura/lectura, manifiestos, asignación de publicación e índice único. **Nunca ejecutes tests destructivos ni descargues respaldos en una BD de producción.**

## Pruebas finales manuales necesarias

Subir PNG+MP4 reales → crear lista → vincular pantalla → publicar → comprobar última versión recibida → cortar WAN → 10 minutos de reproducción → reconectar → verificar backup cifrado → restaurar en copia QA. Repetir en tu H96 y teléfonos/tablets objetivo.

0.8: `wall-creatives.php` verifica videowalls, generador de intros y URLs HTTPS de streaming.
