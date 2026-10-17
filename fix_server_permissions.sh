#!/usr/bin/env bash
# Fix ownership/permissions for stream_production so:
#   - ec2-user can scp/overwrite code without "Permission denied"
#   - apache/PHP can still write runtime folders (data/, music/)
#
# Model:
#   - shared group: apache
#   - dirs  2775 (setgid + group-write → new files inherit group apache)
#   - files 664  (group-write → either owner can overwrite)
#   - code owned by ec2-user:apache
#   - data/ + music/ owned by apache:apache (PHP primary writer)
#
# One-time (if not already): put deploy user in the apache group, then re-login:
#   sudo usermod -aG apache ec2-user
#   # log out of ssh and back in, then: id   # should list apache
#
# Also set a group-writable umask for new scp files (optional but recommended):
#   echo 'umask 002' >> ~/.bashrc
#
# Usage (on the prod server):
#   bash fix_server_permissions.sh
#   bash fix_server_permissions.sh /var/www/html/psistorm/stream_production apache
#
# Or from your laptop:
#   ssh -i "~/Downloads/REVIEW/2024/Other/karl ptfs aws.pem" ec2-user@psistorm.com 'bash -s' < fix_server_permissions.sh

set -euo pipefail

APP_DIR="${1:-/var/www/html/psistorm/stream_production}"
DEPLOY_USER="${SUDO_USER:-${USER:-ec2-user}}"

WEB_USER="${2:-}"
if [ -z "$WEB_USER" ]; then
    WEB_USER="$(ps -eo user=,comm= 2>/dev/null \
        | awk '$2 ~ /httpd|apache2|nginx/ && $1 != "root" {print $1}' \
        | sort -u | head -n1 || true)"
fi
WEB_USER="${WEB_USER:-apache}"
WEB_GROUP="$WEB_USER"

if [ ! -d "$APP_DIR" ]; then
    echo "ERROR: app dir not found: $APP_DIR" >&2
    exit 1
fi
if ! id "$WEB_USER" >/dev/null 2>&1; then
    echo "ERROR: web user '$WEB_USER' does not exist. Pass the correct one as arg 2." >&2
    exit 1
fi
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    DEPLOY_USER="ec2-user"
fi

echo "App dir     : $APP_DIR"
echo "Web user    : $WEB_USER (group $WEB_GROUP)"
echo "Deploy user : $DEPLOY_USER"
echo

# --- one-time membership hint ---
if ! id -nG "$DEPLOY_USER" 2>/dev/null | tr ' ' '\n' | grep -qx "$WEB_GROUP"; then
    echo "NOTE: $DEPLOY_USER is not in group $WEB_GROUP yet."
    echo "      Run once:  sudo usermod -aG $WEB_GROUP $DEPLOY_USER"
    echo "      Then log out of SSH and back in before relying on group write."
    echo
fi

# --- whole tree: shared group + setgid dirs + group-writable files ---
echo "Fixing tree under $APP_DIR ..."
sudo chgrp -R "$WEB_GROUP" "$APP_DIR"
sudo find "$APP_DIR" -type d -exec chmod 2775 {} +
sudo find "$APP_DIR" -type f -exec chmod 664 {} +

# Code / configs: deploy user owns so scp overwrites cleanly.
# Skip runtime dirs (handled below).
echo "Setting code ownership to $DEPLOY_USER:$WEB_GROUP ..."
sudo find "$APP_DIR" \
    \( -path "$APP_DIR/data" -o -path "$APP_DIR/data/*" \
       -o -path "$APP_DIR/music" -o -path "$APP_DIR/music/*" \) -prune \
    -o -exec chown "$DEPLOY_USER:$WEB_GROUP" {} +

# Runtime folders PHP writes: web user owns, still group-writable for deploy.
for sub in data music; do
    dir="$APP_DIR/$sub"
    if [ -d "$dir" ]; then
        echo "Fixing runtime $dir → $WEB_USER:$WEB_GROUP ..."
        sudo chown -R "$WEB_USER:$WEB_GROUP" "$dir"
        sudo find "$dir" -type d -exec chmod 2775 {} +
        sudo find "$dir" -type f -exec chmod 664 {} +
    else
        echo "Skip (missing): $dir"
    fi
done

echo
echo "Done."
echo "  scp as $DEPLOY_USER should overwrite code files."
echo "  PHP as $WEB_USER should write data/ and music/."
echo "  If group membership was just added, re-login SSH first."
