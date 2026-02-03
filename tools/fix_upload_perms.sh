#!/usr/bin/env bash
set -euo pipefail

# Jalankan dari root project AMS (atau sesuaikan BASE)
BASE="${1:-$(pwd)}"
UPLOAD_DIR="$BASE/storage/uploads"

if [ ! -d "$UPLOAD_DIR" ]; then
  echo "Folder upload tidak ditemukan: $UPLOAD_DIR" >&2
  exit 1
fi

# Folder: 755
find "$UPLOAD_DIR" -type d -print0 | xargs -0 chmod 755

# File: 644
find "$UPLOAD_DIR" -type f -print0 | xargs -0 chmod 644

echo "OK: permission upload diset (dir=755, file=644) di $UPLOAD_DIR"
