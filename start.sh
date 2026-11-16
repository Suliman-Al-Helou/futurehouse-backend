#!/bin/sh
set -e

echo "==> Starting Courses Platform (Free Unified Web & Queue Container)..."

# Run database migrations safely
if [ -n "$DB_DATABASE" ] || [ -n "$DB_URL" ]; then
    echo "==> Running database migrations..."
    php artisan migrate --force || true
fi

# Signal any prior queue workers to finish
php artisan queue:restart || true

# Start the background queue worker in the background
echo "==> Starting background queue worker..."
php artisan queue:work --tries=3 --timeout=3600 --rest=1 &

# Determine port (Render assigns $PORT dynamically, defaults to 8000)
PORT_TO_USE="${PORT:-8000}"
echo "==> Starting Laravel web server on port ${PORT_TO_USE}..."

# Exec replaces shell with web server as primary foreground process
exec php artisan serve --host=0.0.0.0 --port="${PORT_TO_USE}"
