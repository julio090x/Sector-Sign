# Integración nativa de Sector Sign en Inventario Pro 360
> **Estado: diseño aprobado como objetivo funcional; integración nativa NO implementada.**
> Actualizado: 2026-10-24. **No presentar este documento como prueba de despliegue.**

## Requisito principal
**Inventario Pro 360 es la única consola para el administrador del negocio**: el menú lateral y las pantallas de Publicidad Digital se abren dentro del propio Inventario, mantienen la sesión y roles de Inventario y permiten operar TODAS las funciones publicitarias sin volver a iniciar sesión o navegar a otro panel.

**Sector Sign** sigue siendo el motor/backend separado, con la base PHP/MySQL de aaPanel, multimedia y reproductores propios. Su panel independiente podría servir a clientes externos, pero no debe ser un paso requerido para el administrador de Inventario Pro.

## Menú nativo propuesto
Inventario Pro 360 → Publicidad Digital
- Resumen: pantallas en línea, campañas activas, contenido por descargar, errores y últimos cambios.
- Biblioteca: subir imágenes, videos MP4/WebM y audio MP3/WAV/OGG/M4A, multiselección, arrastrar y soltar, carpetas, etiquetas, eliminar/recuperar, cuotas y deduplicación.
- Estudio: diseño del cartel, intro, voz sintetizada/narración, subtítulos, pistas independientes y mezcla de audio; previsualización y exportación según capacidades reales.
- Listas: crear, duplicar, mover/copiar, ordenar anuncios; duraciones y transiciones; deshacer/rehacer en editor.
- Programación: una o más listas por franja de días/horas, rotación, prioridad, programación nocturna, vista semanal, lista de respaldo.
- Pantallas: vincular por código/QR, estado/última señal, versión, campaña/descargas, previsualización, reiniciar reproductor, pausar/reanudar y revocar.
- Ubicaciones y grupos: empresa → sede/local → piso → pasillo → pantallas; espejo, independiente, videowall y programación por grupos.
- Publicaciones: borradores/validación, publicar, historial, comparar/volver a publicación, confirmaciones.
- Integración: conectar productos/comparar cambios y advertir promociones vencidas, precio desactualizado o no sincronizado.
- Ajustes: idiomas, roles, branding, almacenamiento S3, respaldo, límites, alertas, diagnósticos/versiones.

## Integración interna
- NO iframe con segunda sesión: **módulo nativo** de Inventario + llamadas a un API-gateway/servicio servidor-a-servidor.
- Inventario autentica al usuario y comprueba **en el backend** permisos de consulta, edición, publicación y administración de pantallas. Nunca confiar solo en botones ocultos.
- API-gateway autentica petición con una identidad de servicio de alcance mínimo, y transporta **actor, organización y correlación** firmados con vencimiento, audiencia, nonce/antirreplay e idempotencia. Claves solo en servidor, no en JS ni repositorios. Auditar toda publicación y vinculación.
- Aplicar aislamiento por empresa/organización en ambos servicios. Nunca compartir sesión o contraseña de Inventario con el reproductor.
- Sector Sign sigue siendo dueño de **archivos, listas, programas, dispositivos, publicaciones y logs**; Inventario sigue siendo dueño de **productos, precios de venta y promociones**. La interfaz solo consume API, nunca accede a la base MySQL del otro proyecto.
- Catalogo público autorizado: ID estable, nombre, imagen aprobada, presentación, precio público, vigencia, cantidad mínima, revision. No exportar costo, margen, Kardex, stock interno, proveedores, clientes, documentos fiscales ni tokens.
- Los cambios de precios generan **borrador/revisión**, sin cambiar silenciosamente anuncios publicados; la publicación automática es opt-in y solo después de pruebas de precios y vencimientos.
- Proteger CDN/media con rutas y permisos adecuados; descarga offline completa antes de activar una campaña. Para live/HLS, indicar necesidad de conexión.
- Despliegue preferido: Inventario en su dominio actual con módulo UI y gateway; Sector Sign puede vivir en dominio/subdominio separado, sin abrir puertos MySQL públicos. CORS/cookies no deben sustituir autenticación de servicio. Sin requerir Node.js en producción.

## Integración con el código real — bloquear hasta inspección
No hay ZIP exacto y vigente de Inventario Pro 360 disponible en este repositorio. Antes de insertar menú o alterar rutas:
1. Obtener el ZIP **canónico vigente de Inventario Pro 360** (no usar versiones antiguas, inventadas o reconstruidas).
2. Inspeccionar AGENTS.md, CLAUDE.md, reglas de módulos, permisos, rutas y JS; identificar punto de menú, controlador, servicios y componentes.
3. Ejecutar Quality Gates/contratos de Inventario **antes** del cambio y repetirlos después, más pruebas de navegador 320/390/768/1440.
4. Implementar módulo nativo y gateway en una rama aislada, con pruebas de una sola sesión y roles. Preservar storage y credenciales, fiscal, Kardex, compras/ventas, FEFO, respaldos y portales.
5. Prueba E2E real: login Inventario → subir imagen/video/audio → crear cartel y lista → arrastrar/reordenar → programar 3 listas → asignar piso/pasillo/grupo → publicar → verificar recepción, versión y reproducción offline → corregir precio público en Inventario → mostrar revisión pendiente → aprobar republicación.
6. Prueba en aaPanel/MySQL real y H96/teléfono/tablet con red remota/local; medir latencia y errores. Verificar backups y restauración.
7. Entregar dos paquetes separados (Inventario actualizado y Sector Sign compatible), manifest/SHA-256, instrucciones de actualizar/rollback y evidencia real. **No declarar producción antes de superar estas pruebas.**

## Definición de terminado
Con el usuario autenticado únicamente en Inventario Pro 360, subir/publicar/programar/administrar pantallas funciona sin login adicional. El motor sigue estable y recupera la última campaña tras perder internet. Los usuarios no autorizados no ven ni modifican publicidad. Los precios solo cambian cuando hay una revisión aprobada. Toda acción guarda usuario, fecha, versión y confirmación de dispositivo.
