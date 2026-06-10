#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"

if [[ ! -d "$ROOT" ]]; then
  echo "Usage: $0 [project-root]" >&2
  echo "Error: project root does not exist: $ROOT" >&2
  exit 1
fi

cd "$ROOT"

echo "Fixing Map Snapshot Service permissions under: $ROOT"

# Directories must be searchable by the web server so PHP-FPM can open entry
# scripts and include recipe/renderer files.
find . \
  \( -path './.git' -o -path './.git/*' -o -path './.superpowers' -o -path './.superpowers/*' -o -path './cache' -o -path './cache/*' \) -prune \
  -o -type d -exec chmod 0755 {} +

# Public/runtime source files should be readable, not executable. Skip cache
# contents because they may be owned by the PHP-FPM user and contain generated
# output; cache/.htaccess is handled below.
find . \
  \( -path './.git' -o -path './.git/*' -o -path './.superpowers' -o -path './.superpowers/*' -o -path './cache' -o -path './cache/*' \) -prune \
  -o -type f -exec chmod 0644 {} +

if [[ -f ./chmod.sh ]]; then
  chmod 0755 ./chmod.sh
fi

if [[ -d ./cache ]]; then
  chmod 0755 ./cache 2>/dev/null || true
  if [[ -f ./cache/.htaccess ]]; then
    chmod 0644 ./cache/.htaccess 2>/dev/null || true
  fi
fi

echo "Done."
echo "Recommended runtime ownership: generated cache directories should remain writable by the PHP-FPM user, usually www-data."
