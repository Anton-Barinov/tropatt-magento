#!/usr/bin/env bash
# Reproducible build of the module archive.
# Usage: bash build.sh   (produces dist/tropatt-magento.zip)
set -euo pipefail
cd "$(dirname "$0")"

mkdir -p dist
rm -f dist/tropatt-magento.zip

rm -rf .build
mkdir -p .build
cp -R app README.md LICENSE .build/

(cd .build && zip -r -X ../dist/tropatt-magento.zip app README.md LICENSE >/dev/null)
rm -rf .build

unzip -t dist/tropatt-magento.zip >/dev/null
echo "Built dist/tropatt-magento.zip"
