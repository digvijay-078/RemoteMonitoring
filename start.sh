#!/usr/bin/env bash
set -e

echo "=== [Railway Startup] Initializing RemoteMonitor Production Stack ==="

# 1. Ensure SQLite or DB exists
mkdir -p database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
touch database/database.sqlite
chmod -R 777 storage database

# 2. Run Database Migrations and Ensure Admin / Seeding
echo "-> Running database migrations..."
php artisan migrate --force || true
php artisan db:seed --class=AdminUserSeeder --force || true

# 3. Start Laravel Backend Service on :8000
echo "-> Starting Laravel HTTP server on 127.0.0.1:8000..."
php artisan serve --host=127.0.0.1 --port=8000 &

# 4. Start Laravel Reverb WebSocket Server on :8081
echo "-> Starting Laravel Reverb WebSockets on 127.0.0.1:8081..."
php artisan reverb:start --host=127.0.0.1 --port=8081 &

# 5. Start Python Stream Hub Engine on :8085
echo "-> Starting Python Stream Hub on 127.0.0.1:8085..."
python3 tools/server_stream_hub.py &

# 6. Start Unified Ingress Proxy on $PORT (Public Entrypoint)
echo "-> Starting Unified Ingress Proxy on port ${PORT:-8088}..."
exec node tools/unified_proxy.cjs
