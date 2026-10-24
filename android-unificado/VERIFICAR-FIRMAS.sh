#!/usr/bin/env bash
set -euo pipefail
if [[ -z "${ANDROID_HOME:-}" ]]; then echo 'Define ANDROID_HOME con la ruta del SDK Android'; exit 2; fi
APKS=(app/build/outputs/apk/centro/debug/*-debug.apk app/build/outputs/apk/receptor/debug/*-debug.apk)
for a in "${APKS[@]}"; do
  [[ -f "$a" ]] || { echo "No existe $a"; exit 2; }
  "$ANDROID_HOME/build-tools/35.0.0/apksigner" verify --verbose --print-certs "$a"
  sha256sum "$a"
done
