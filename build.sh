#!/usr/bin/env bash
#
# Build the React SPA and publish it into the Laravel app so the whole
# thing is served from a single origin (php artisan serve on :8000).
#
set -e
cd "$(dirname "$0")"

echo "==> Building frontend (Vite production)…"
( cd frontend && npm run build )

echo "==> Publishing assets into Laravel public/…"
rm -rf backend/public/assets
cp -r frontend/dist/assets backend/public/assets

# Compiled SPA shell — served by the catch-all web route.
cp frontend/dist/index.html backend/resources/spa.html

# Any other root-level static files from the build (favicon, svg, etc.)
find frontend/dist -maxdepth 1 -type f ! -name index.html -exec cp {} backend/public/ \;

echo "==> Done."
echo "    Start the app with:  cd backend && php artisan serve"
echo "    Then open:           http://localhost:8000"
