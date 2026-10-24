#!/bin/sh
set -eu
BASE="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
find "$BASE" -name '*.php' -type f -print | while IFS= read -r file; do php -l "$file" >/dev/null; done
echo 'PASS: sintaxis de todos los PHP'
php "$BASE/tests/contracts.php"
php "$BASE/tests/program-validation.php"
if command -v node >/dev/null 2>&1; then
  node --check "$BASE/public/assets/app.js"
  node --check "$BASE/public/assets/player.js"
  node --check "$BASE/public/sw-player.js"
  node "$BASE/tests/schedule.node.js"
  node --check "$BASE/public/assets/programs-ui.js"
  node "$BASE/tests/programs.node.js"
  echo 'PASS: sintaxis JavaScript (solo herramienta de pruebas; no se necesita Node para operar)'
fi
if [ -e "$BASE/private/config.php" ]; then echo 'WARNING: config.php contiene credenciales; nunca lo empaquetes'; fi

if command -v curl >/dev/null 2>&1; then sh "$BASE/tests/http-smoke.sh"; fi
