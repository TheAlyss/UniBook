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
chmod -R 777 "$DATA" "$APP/storage" "$APP/bootstrap/cache"
cd "$APP"
php artisan migrate --force
