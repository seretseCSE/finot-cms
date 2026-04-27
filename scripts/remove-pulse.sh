#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# Remove Laravel Pulse from Production
# =============================================================================
# Run this script before deploying to production if Pulse was only needed
# for staging performance profiling.
#
# Usage:
#   chmod +x scripts/remove-pulse.sh
#   ./scripts/remove-pulse.sh
# =============================================================================

echo "==> Removing Laravel Pulse..."

# 1. Drop Pulse tables
echo "==> Dropping Pulse database tables..."
php artisan db:wipe --table="pulse_aggregates" --force 2>/dev/null || true
php artisan db:wipe --table="pulse_entries" --force 2>/dev/null || true
php artisan db:wipe --table="pulse_values" --force 2>/dev/null || true

# Safer: use raw SQL via migration rollback
php artisan migrate:rollback --path=database/migrations/*_create_pulse_tables.php --force 2>/dev/null || true

# 2. Remove published config
echo "==> Removing published Pulse assets..."
rm -f config/pulse.php
rm -rf resources/views/vendor/pulse

# 3. Remove Pulse migration files
echo "==> Removing Pulse migration files..."
find database/migrations -name '*_create_pulse_tables.php' -delete 2>/dev/null || true

# 4. Uninstall composer package
echo "==> Uninstalling laravel/pulse via Composer..."
composer remove laravel/pulse --no-interaction

# 5. Clear caches
echo "==> Clearing application caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo "==> Laravel Pulse has been removed."
echo "    Review your routes and service providers if you added Pulse-specific logic."
