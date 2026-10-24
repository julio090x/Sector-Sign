# Informe de verificación — Sector Sign 0.7.0

Fecha: 24 de octubre de 2026. Estado: entrega para piloto; no certificación de producción ni prueba física de H96.

## Evidencia real

Repositorio independiente: https://github.com/julio090x/Sector-Sign

- Compilación Android exitosa: https://github.com/julio090x/Sector-Sign/actions/runs/40092956507
- Importación íntegra de fuentes y MySQL/HTTP: https://github.com/julio090x/Sector-Sign/actions/runs/40095339301
- Commit de fuentes importadas: bd9c8d9b11a9c63563b9f1a0d4b84206e57d1285.

## Resultados

| Control | Resultado |
|---|---|
| PHP y JavaScript: sintaxis | Aprobado |
| Contratos previos (archivos, firmas, cifrado) | 11 aprobados |
| Validación PHP de programas | 16 aprobados |
| Horarios anteriores | 6 aprobados |
| Programación JS (tres listas, prioridad, medianoche, fechas, zona horaria) | 32 aprobados |
| Coherencia de versiones | 15 aprobados |
| MySQL 8.4.13: tablas, transacciones, unicidad, rollback | Aprobado en GitHub Actions |
| HTTP con MySQL real: cuentas, CSRF, roles, separación entre organizaciones, vinculación, publicar, revisión, telemetría, ACK, revocación | 16 aprobados |
| Repetición de migración sobre esquema actual | Aprobado dos veces; sin cambios pendientes |
| Compilación de dos APK Debug y dos Release | Aprobada en GitHub Actions |
| Dos APK Release entregadas: firmas v2/v3 | Verificadas con apksigner oficial |
| Interfaz en 1440×1000, 768×1024 y 390×844 | Sin desbordamiento horizontal ni errores JS en flujos comprobados |

Las pruebas visuales utilizan los archivos reales de interfaz con datos simulados y respuestas API controladas. No son una prueba de servidor real en navegador ni de hardware físico. Cubren editor de horarios, orden de tres listas, guardar, publicar, vista previa, dispositivos y navegación ES/EN/PT.

El test HTTP/MySQL sí utilizó un servidor PHP y base MySQL reales, aislados en CI, con datos ficticios. Nunca se conectó a la base del negocio ni se modificó SectorPay o Inventario Pro 360.

## Firmas y archivos

Certificado público SHA-256 de ambas APK: `21b49a67e68cfa634453eac258e79f8fc243695fe729b6506ae564271d8d8457`.

- Mando SHA-256: `2c11398a82f2a9637707d240ffba0d37a2e162bc8a86162fda46552f32cc2102`.
- Reproductor SHA-256: `4a45ae6c24a226bd2376edacc7c7c3973044581d29eacaa33a4d6a52b697335c`.

El respaldo privado de firma se entrega exclusivamente al titular, separado del código. No está en GitHub ni en los ZIP públicos de aplicación.

## Límites conocidos

No se desplegó en aaPanel del usuario ni se probó el H96. Falta la conexión del Inventario Pro vigente y validar OAuth, S3/FTPS/Drive con las cuentas reales. La migración Angular es parcial y los idiomas de pantallas antiguas no están traducidos íntegramente. Las APK reutilizan WebView y no incluyen servicio de arranque automático. HTTPS y almacenamiento persistente del navegador son necesarios para el modo offline web. La simulación horaria aprueba lógica local sin red, no acredita recuperación tras reinicio físico ni persistencia en todos los equipos.

Estado en línea indica comunicación reciente del reproductor, no energía o entrada HDMI del monitor. La última captura/reporte no es vídeo en vivo. La sincronización online usa consultas periódicas (aproximadamente 5 s); los archivos grandes demoran según la red. Editar una lista no cambia un programa publicado hasta volver a publicarlo.
