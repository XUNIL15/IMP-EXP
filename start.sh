#!/bin/bash

MYSQL_DATADIR="/home/runner/mysql-data"
MYSQL_SOCKET="/run/mysqld/mysqld.sock"

# Ensure directories exist
mkdir -p "$MYSQL_DATADIR"
mkdir -p /run/mysqld
chmod 777 /run/mysqld

# Kill any leftover mysql processes and stale sockets
pkill -9 mysqld 2>/dev/null || true
rm -f "$MYSQL_SOCKET"
sleep 1

# Start MariaDB (auto-initializes data dir on first run)
echo "[startup] Starting MariaDB..."
mysqld \
  --datadir="$MYSQL_DATADIR" \
  --socket="$MYSQL_SOCKET" \
  --port=3306 \
  --skip-grant-tables \
  --user=runner \
  2>>"$MYSQL_DATADIR/mysqld.err" &

# Wait for socket to be ready (up to 40 seconds)
echo "[startup] Waiting for MariaDB to start..."
for i in $(seq 1 40); do
  if [ -S "$MYSQL_SOCKET" ]; then
    echo "[startup] MariaDB ready after ${i}s"
    break
  fi
  sleep 1
done

if [ ! -S "$MYSQL_SOCKET" ]; then
  echo "[startup] ERROR: MariaDB did not start. Logs:"
  tail -20 "$MYSQL_DATADIR/mysqld.err"
  exit 1
fi

# Load schema if database doesn't exist yet
DB_EXISTS=$(mysql -u root --socket="$MYSQL_SOCKET" -e "SHOW DATABASES LIKE 'import_export';" 2>/dev/null | grep import_export || echo "")

if [ -z "$DB_EXISTS" ]; then
  echo "[startup] Loading database schema..."
  mysql -u root --socket="$MYSQL_SOCKET" < /home/runner/workspace/database.sql 2>&1 && \
    echo "[startup] Database schema loaded successfully." || \
    echo "[startup] WARNING: Some schema errors (may be non-fatal)"
else
  echo "[startup] Database 'import_export' already exists, skipping schema load."
fi

echo "[startup] Starting PHP server on 0.0.0.0:5000..."
exec php -S 0.0.0.0:5000 -t /home/runner/workspace 2>&1
