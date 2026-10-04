#!/usr/bin/env bash
# Build the distributable extension zip (forum-root layout: ext/...).
# Output is gitignored; attach it to a GitHub release, don't commit it.
set -euo pipefail
cd "$(dirname "$0")/.."

VER=$(python3 -c 'import json; print(json.load(open("ext/techwiz18/opencredits/composer.json"))["version"])')
OUT="dist/opencredits-${VER}.zip"

mkdir -p dist
rm -f "$OUT"
zip -qr "$OUT" ext/techwiz18/opencredits -x '*/.*'
unzip -l "$OUT" | tail -n 3
echo "built $OUT"
