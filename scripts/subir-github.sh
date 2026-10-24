#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ $# -ne 1 ]]; then
  echo "Uso: bash scripts/subir-github.sh https://github.com/TU_USUARIO/sector-sign.git" >&2
  exit 2
fi
url="$1"
if [[ ! "$url" =~ ^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(\.git)?$ ]]; then
  echo "Debes indicar una URL HTTPS válida de un repositorio GitHub que ya hayas creado." >&2
  exit 2
fi
if [[ $(git status --porcelain) ]]; then
  echo "Hay cambios sin confirmar: confirma los cambios antes de publicar." >&2
  exit 1
fi
if git remote get-url origin >/dev/null 2>&1; then git remote set-url origin "$url";else git remote add origin "$url";fi
git push --set-upstream origin main --tags
echo "Proyecto independiente publicado. Ve a Actions para obtener las dos APK debug firmadas."
