#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(tr -d '\r\n' < "$ROOT/VERSION")"
DIST="$ROOT/dist"; WORK="$ROOT/build/.work"
rm -rf "$DIST" "$WORK"; mkdir -p "$DIST" "$WORK/component" "$WORK/package"
cp -R "$ROOT/component/." "$WORK/component/"
( cd "$WORK/component" && find . -type f -print0 | sort -z | xargs -0 zip -X -q "$DIST/com_decarodraw_${VERSION}.zip" )
cp "$ROOT/package/pkg_decarodraw.xml" "$WORK/package/pkg_decarodraw.xml"
cp "$DIST/com_decarodraw_${VERSION}.zip" "$WORK/package/com_decarodraw.zip"
( cd "$WORK/package" && find . -type f -print0 | sort -z | xargs -0 zip -X -q "$DIST/pkg_decarodraw_${VERSION}.zip" )
( cd "$DIST" && sha256sum "com_decarodraw_${VERSION}.zip" "pkg_decarodraw_${VERSION}.zip" > SHA256SUMS.txt )
echo "Built Draw by xdecaro $VERSION"
