#!/usr/bin/env bash
# Crea lo zip installabile del plugin.
set -euo pipefail

cd "$(dirname "$0")/.."
nome="geo-landing-pages"
zip -r "${nome}.zip" "$nome" \
	-x "${nome}/build.sh" \
	-x "${nome}/.git/*" \
	-x "*.DS_Store" >/dev/null

echo "Creato: $(pwd)/${nome}.zip"
