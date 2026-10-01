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
export PHP_CLI_SERVER_WORKERS=4

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
PHP_CLI_SERVER_WORKERS=4
EOF
fi

# 2. Ensure directories and SQLite DB exist
mkdir -p database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
touch /app/database/database.sqlite || touch database/database.sqlite
chmod -R 777 storage database bootstrap/cache || true

# 3. Run Database Migrations and Seeding
echo "-> Running database migrations..."
php artisan migrate --force --no-interaction || true
php artisan db:seed --class=AdminUserSeeder --force --no-interaction || true

# 4. Start Laravel Backend Service on 0.0.0.0:8000
echo "-> Starting Laravel HTTP server with 4 workers on 0.0.0.0:8000..."
php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/laravel_serve.log 2>&1 &

# 5. Start Laravel Reverb WebSocket Server on 0.0.0.0:8081
echo "-> Starting Laravel Reverb WebSockets on 0.0.0.0:8081..."
php artisan reverb:start --host=0.0.0.0 --port=8081 > storage/logs/reverb.log 2>&1 &

# 6. Start Python Stream Hub Engine on 0.0.0.0:8085
if [ -f tools/server_stream_hub.py ]; then
    echo "-> Starting Python Stream Hub on 0.0.0.0:8085..."
    python3 tools/server_stream_hub.py > storage/logs/stream_hub.log 2>&1 &
fi

# Wait 3 seconds for background processes to bind
sleep 3

# Verify Laravel is running locally
curl -s http://127.0.0.1:8000/ > /dev/null && echo "-> [OK] Laravel backend is responding on port 8000" || echo "-> [WAIT] Laravel backend starting up..."

# 7. Start Unified Ingress Proxy on $PORT (Public Entrypoint)
echo "-> Starting Unified Ingress Proxy on port ${PORT:-8080}..."
exec node tools/unified_proxy.cjs
