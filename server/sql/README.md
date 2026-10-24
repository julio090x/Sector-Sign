# Base de datos de Sector Sign 0.4.0

- `schema.mysql.sql`: tablas vacías **MySQL 8/MariaDB 10.5+**. El instalador `public/install.php` la importa automáticamente; crea una base MySQL vacía en aaPanel, no una base de Inventario Pro.
- `upgrade-0.3-to-0.4.mysql.sql`: **solo referencia de actualización**. Preferir `php tools/upgrade.php` para una actualización idempotente desde 0.3.
- `tests/mysql-smoke.php`: contrato SQL aislado transaccional; se niega a ejecutarse si la base no termina en `_qa`.

No contienen cuentas, anuncios, datos de clientes, contraseñas ni claves privadas.
