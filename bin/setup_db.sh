#!/usr/bin/env bash
# TradeERP — local development database setup
# Creates the test database + user used by bin/test.php
#
# Usage:  bash bin/setup_db.sh   (run as a user that can sudo mysql, or root)
# Production: use your host's control panel instead — never this script.

set -euo pipefail

DB_NAME="${1:-trader_erp}"
DB_USER="${2:-root}"
DB_PASS="${3:-}"

sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "Database '${DB_NAME}' ready (user: ${DB_USER}). Run: php bin/test.php"
# Scratch DB for the backup-restore round-trip test
sudo mysql -e "CREATE DATABASE IF NOT EXISTS erp_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON erp_restore_test.* TO '${DB_USER}'@'localhost'; GRANT ALL PRIVILEGES ON erp_restore_test.* TO '${DB_USER}'@'127.0.0.1'; FLUSH PRIVILEGES;" || true
