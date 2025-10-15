#!/usr/bin/env bash
set -euo pipefail
URL="${1:-http://127.0.0.1:5000/health}"
if command -v curl >/dev/null 2>&1; then
  curl -fsS "$URL" > /dev/null
else
  wget -qO- "$URL" > /dev/null
fi
