# Sector Sign: Angular + TypeScript (migración controlada)

Este workspace crea **dos frontends Angular** (`centro` y `receptor`) y un directorio compartido (`libs/compartido`). Usa Angular 21 LTS y TypeScript, sin Node en producción. Una vez compilado, el resultado estático puede alojarse en aaPanel junto al backend PHP/MySQL. El mismo sitio puede instalarse como PWA y abrirse desde los dos envoltorios Android.

## Estado real

**IMPORTANTE:** el shell y las utilidades nuevas están en Angular/TypeScript, pero las funciones de negocio que ya estaban probadas (listas, pantallas, sincronización y offline) siguen en los archivos JS existentes `server/public/assets/app.js` y `player.js`. Eso evita perder funcionalidades mientras se migran módulo por módulo; todavía NO es una migración total de todo el frontend a Angular. Para operar, puedes continuar usando `/index.php` y `/player.php` como hasta ahora.

## Dependencias para desarrollo

Angular 21, Node 22.12+ (para instalar/desarrollar, NO para operar el servidor), npm, TypeScript 5.9. Las librerías no están incluidas en el ZIP y se descargan desde npm al ejecutar `npm install`. Para evitar comprometer el VPS, desarrolla y compila en una computadora o CI, y sube solo `dist` al servidor.

Desde esta carpeta:

```bash
npm install
npm run build
```

Como cliente web, copia el resultado `dist/centro/browser/` a `server/public/angular/centro/` y `dist/receptor/browser/` a `server/public/angular/receptor/`. Abre `/angular/centro/index.html` o `/angular/receptor/index.html`. Requieren misma raíz/origen que `/api.php` y los scripts JS existentes (no separar a un dominio sin configurar API/CORS y cookies).

* En entorno de desarrollo inicia el backend local PHP en `http://127.0.0.1:8787` y ejecuta `npm run start:centro` o `npm run start:receptor`.
* Conserva `/index.php` y `/player.php` como interfaces de producción probadas hasta completar pruebas E2E del nuevo shell.
* La reproducción offline en ruta Angular requiere adaptar el service worker para precachear el shell y los chunks de Angular; **el modo offline ya probado está en `/player.php`**, no en el shell Angular nuevo.
* No hemos ejecutado `npm install` ni compilado Angular en este entorno, pues no hay acceso a los registros externos de npm. Las fuentes y configuración se entregan para compilar/verificar en un entorno con internet.
