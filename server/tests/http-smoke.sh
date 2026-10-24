#!/bin/sh
# Run ONLY in an uninstalled copy; creates/removes install.key in the current fixture.
set -eu
BASE="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
if [ -f "$BASE/private/config.php" ]; then echo 'SKIP: no ejecutar en una instalación real';exit 0;fi
if [ -f "$BASE/private/install.key" ]; then echo 'SKIP: install.key ya existe; no se modifica';exit 0;fi
PORT=18943
php -S 127.0.0.1:$PORT -t "$BASE/public" > /tmp/sector-sign-smoke.log 2>&1 &
SERVER=$!
trap 'kill "$SERVER" 2>/dev/null || :; rm -f "$BASE/private/install.key"' EXIT INT TERM
n=0;until curl -fs "http://127.0.0.1:$PORT/player.php" >/dev/null;do n=$((n+1));[ "$n" -lt 15 ]||{ cat /tmp/sector-sign-smoke.log;exit 1;};sleep 0.2;done
for act in auth.login media.delete screens.unpair player.register playlist.publish;do
 code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/api.php?action=$act")
 [ "$code" = 405 ]||{ echo "FAIL: GET $act returned $code";exit 1;};echo "PASS: GET $act no modifica el estado ($code)";
done
code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/api.php?action=player.sync")
[ "$code" = 503 ]||{ echo "FAIL: player.sync GET returned $code; expected uninstalled=503";exit 1;};echo 'PASS: player.sync conserva GET como lectura'
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST "http://127.0.0.1:$PORT/api.php?action=health")
[ "$code" = 405 ]||{ echo "FAIL: POST health returned $code";exit 1;};echo 'PASS: POST health no permitido'
code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/install.php")
[ "$code" = 200 ]||{ echo "FAIL: installer returned $code";exit 1;};echo 'PASS: Instalador responde en instalación nueva'
