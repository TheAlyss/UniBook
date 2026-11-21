#!/bin/bash
# Azure App Service startup command: /home/site/wwwroot/deploy/azure/startup.sh
# Runs every time the app starts. /home/site/wwwroot is replaced on each deploy,
# so the database and photos live in /home/data, which is kept.
set -e

APP=/home/site/wwwroot
DATA=/home/data

# 1. Serve Laravel's public/ folder instead of wwwroot.
cp "$APP/deploy/azure/nginx.conf" /etc/nginx/sites-available/default
service nginx reload

# 2. Persistent SQLite database and photo folders.
mkdir -p "$DATA/covers" "$DATA/scans"
[ -f "$DATA/database.sqlite" ] || touch "$DATA/database.sqlite"
rm -rf "$APP/public/uploads/covers" "$APP/storage/app/private/scans"
ln -s "$DATA/covers" "$APP/public/uploads/covers"
ln -s "$DATA/scans" "$APP/storage/app/private/scans"

# 3. Writable folders, then create or update the tables.
# Only the data folders and the database file: a recursive chmod over every photo on
# Azure's network storage made each cold start slower as more photos were uploaded.
# New photos are created by PHP itself, so they are writable without it.
chmod 777 "$DATA" "$DATA/covers" "$DATA/scans"
chmod 666 "$DATA/database.sqlite"
chmod -R 777 "$APP/storage" "$APP/bootstrap/cache"
cd "$APP"
php artisan migrate --force

# 4. Cache config, routes and views once per start, so each request reads fewer files.
php artisan config:cache
php artisan route:cache
php artisan view:cache
