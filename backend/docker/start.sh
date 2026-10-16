#!/bin/sh
# Container entrypoint for the hosted demo. See Dockerfile.
set -e

# Hosted MySQL (Aiven) needs its CA certificate; pass the PEM text in DB_SSL_CA_PEM.
if [ -n "$DB_SSL_CA_PEM" ]; then
  printf '%s\n' "$DB_SSL_CA_PEM" > storage/certs/ca.pem
  export DB_SSL_CA=storage/certs/ca.pem
fi

php artisan config:cache
php artisan route:cache
php artisan storage:link >/dev/null 2>&1 || true
php artisan migrate --force

# First boot only: fill an empty demo database (needs DEMO_BOOTSTRAP=true). Runs in the
# background so the web server is reachable while it works.
php artisan erp:demo-bootstrap >> storage/logs/demo-bootstrap.log 2>&1 &

php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &
php artisan schedule:work &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
