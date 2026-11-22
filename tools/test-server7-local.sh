#!/usr/bin/env bash
set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
manifest="$(mktemp "$repo_dir/.composer-server7.XXXXXX.json")"
vendor_dir="$(mktemp -d "${TMPDIR:-/tmp}/engine-ws-server7-vendor.XXXXXX")"
lock_file="${manifest%.json}.lock"

cleanup() {
    rm -f "$manifest" "$lock_file"
    rm -rf "$vendor_dir"
}
trap cleanup EXIT

cp "$repo_dir/composer.json" "$manifest"
COMPOSER="$manifest" composer config minimum-stability dev
COMPOSER="$manifest" composer config prefer-stable true

COMPOSER="$manifest" composer config repositories.server7-server \
    '{"type":"path","url":"../../localzet/Server","options":{"symlink":true,"versions":{"localzet/server":"7.2.2"}}}'
COMPOSER="$manifest" composer config repositories.server7-engine \
    '{"type":"path","url":"../Engine","options":{"symlink":true,"versions":{"triangle/engine":"9.0.x-dev"}}}'
COMPOSER="$manifest" composer config repositories.server7-router \
    '{"type":"path","url":"../Component-Router","options":{"symlink":true,"versions":{"triangle/router":"2.0.x-dev"}}}'
COMPOSER="$manifest" composer config repositories.server7-middleware \
    '{"type":"path","url":"../Component-Middleware","options":{"symlink":true,"versions":{"triangle/middleware":"2.0.x-dev"}}}'

COMPOSER="$manifest" COMPOSER_VENDOR_DIR="$vendor_dir" composer update \
    --with-all-dependencies --prefer-dist --no-interaction --no-progress --no-scripts

ENGINE_WS_VENDOR_AUTOLOAD="$vendor_dir/autoload.php" \
    "$vendor_dir/bin/phpunit" --no-configuration --bootstrap "$vendor_dir/autoload.php" tests "$@"
