#!/usr/bin/env bash
set -e

echo "=== [Railway Startup] Initializing RemoteMonitor Production Stack ==="

# 1. Fallback environment variables (Zero-config safety)
export APP_NAME="${APP_NAME:-RemoteMonitor}"
export APP_ENV="${APP_ENV:-production}"
export APP_KEY="${APP_KEY:-base64:lXqXqObkXby+VmxpT/2Q83Qp2Fak7ImhoxPS3EdLjkE=}"
export APP_DEBUG="${APP_DEBUG:-true}"
export APP_URL="${APP_URL:-http://localhost}"
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-/app/database/database.sqlite}"
export BROADCAST_CONNECTION="${BROADCAST_CONNECTION:-reverb}"
export REVERB_APP_ID="${REVERB_APP_ID:-889811}"
export REVERB_APP_KEY="${REVERB_APP_KEY:-nw2zhrpowiazy7xm9esc}"
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-iiuv1ixpvthgskqhvn3v}"
export REVERB_HOST="127.0.0.1"
export REVERB_PORT="8081"
export REVERB_SCHEME="http"

# Create .env if missing
if [ ! -f .env ]; then
    cat <<EOF > .env
APP_NAME=RemoteMonitor
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=true
APP_URL=http://localhost
DB_CONNECTION=sqlite
DB_DATABASE=/app/database/database.sqlite
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=${REVERB_APP_ID}
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_HOST=127.0.0.1
REVERB_PORT=8081
REVERB_SCHEME=http
EOF
fi

# 2. Ensure directories and SQLite DB exist
mkdir -p database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch database/database.sqlite || true
chmod -R 777 storage database bootstrap/cache || true

# 3. Run Database Migrations and Seeding
echo "-> Running database migrations..."
php artisan migrate --force --no-interaction || true
php artisan db:seed --class=AdminUserSeeder --force --no-interaction || true

# 4. Start Laravel Backend Service on :8000
echo "-> Starting Laravel HTTP server on 127.0.0.1:8000..."
php artisan serve --host=127.0.0.1 --port=8000 &

# 5. Start Laravel Reverb WebSocket Server on :8081
echo "-> Starting Laravel Reverb WebSockets on 127.0.0.1:8081..."
php artisan reverb:start --host=127.0.0.1 --port=8081 &

# 6. Start Python Stream Hub Engine on :8085
if [ -f tools/server_stream_hub.py ]; then
    echo "-> Starting Python Stream Hub on 127.0.0.1:8085..."
    python3 tools/server_stream_hub.py &
fi

# Wait 2 seconds for background processes to bind
sleep 2

# 7. Start Unified Ingress Proxy on $PORT (Public Entrypoint)
echo "-> Starting Unified Ingress Proxy on port ${PORT:-8088}..."
exec node tools/unified_proxy.cjs
