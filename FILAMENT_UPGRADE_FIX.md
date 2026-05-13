
# Filament Upgrade Fix - Manual Approach

## Issues Identified

### 1. **PCNTL Extension Missing**
- **Error:** `laravel/horizon v5.46.0 requires ext-pcntl * -> it is missing from your system`
- **Status:** `PCNTL: NOT LOADED`
- **Impact:** Prevents Horizon from running and blocks upgrade

### 2. **Upgrade Script Path Issue**
- **Error:** `vendor/bin/filament-v5 : The term 'vendor/bin/filament-v5' is not recognized`
- **Cause:** Windows path resolution issue

## Immediate Fix: Manual Upgrade

Since automated upgrade fails, use this manual approach:

### Step 1: Update Composer Dependencies
```bash
# Update Filament to v5.2
composer require filament/filament:"^5.2"

# Update HTML Sanitizer
composer update symfony/html-sanitizer "^8.0"

# Update all Filament packages
composer update "filament/*" --with-all-dependencies
```

### Step 2: Handle PCNTL Extension (Temporary Workaround)
```bash
# Option A: Ignore platform requirement (temporary)
composer update --ignore-platform-req=ext-pcntl

# Option B: Update composer.json to remove Horizon temporarily
```

### Step 3: Clear Caches and Rebuild
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

npm install
npm run build
```

### Step 4: Test Critical Areas
- Login/logout functionality
- Form submissions
- Table operations
- File uploads

## PCNTL Extension Fix (Permanent Solution)

### For XAMPP on Windows:
1. **Edit php.ini:**
   ```
   ; Enable PCNTL extension
   extension=pcntl
   ```

2. **Restart Apache/XAMPP**

3. **Verify Installation:**
   ```bash
   php -r "echo 'PCNTL: ' . (extension_loaded('pcntl') ? 'LOADED' : 'NOT LOADED');"
   ```

## Alternative: Remove Horizon Temporarily

If PCNTL cannot be enabled:
```bash
# Temporarily remove Horizon
composer remove laravel/horizon

# Complete upgrade
composer require filament/filament:"^5.2"
composer update "filament/*"

# Reinstall Horizon after upgrade
composer require laravel/horizon "^5.0"
```

## Verification Commands

After upgrade, verify:
```bash
# Check Filament version
composer show filament/filament | grep "versions"

# Check HTML Sanitizer version
composer show symfony/html-sanitizer | grep "versions"

# Test application
php artisan serve --port=8000
```

## Rollback Plan

If issues occur:
```bash
# Restore composer files
git checkout HEAD -- composer.json composer.lock

# Reinstall dependencies
composer install

# Clear caches
php artisan config:clear
php artisan cache:clear
```

## Expected Results

After successful manual upgrade:
- Filament: v5.2.x
- Symfony HTML Sanitizer: v8.0+
- All new features available
- Improved security and performance
