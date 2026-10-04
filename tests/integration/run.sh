#!/bin/sh
# Boots WordPress with WooCommerce and this plugin, then checks the plugin against it.
# Needs Node 24 and PHP. Usage: tests/integration/run.sh [php-version]
set -eu
cd "$(dirname "$0")/../.."
port=9412
# A blueprint's own PHP preference beats the --php flag, so the version goes in the blueprint.
blueprint=$(mktemp).json
sed "s/\"steps\"/\"preferredVersions\": { \"php\": \"${1:-8.3}\", \"wp\": \"latest\" }, \"steps\"/" \
    tests/integration/blueprint.json > "$blueprint"
npx -y @wp-playground/cli@latest server --port="$port" --verbosity=quiet --blueprint="$blueprint" \
    --mount "$PWD:/wordpress/wp-content/plugins/ng-postcode-for-woocommerce" \
    --mount "$PWD/tests/integration/probe.php:/wordpress/ng-probe.php" \
    --mount "$PWD/tests/integration/setup.php:/wordpress/ng-setup.php" >/dev/null 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null; rm -f "$blueprint"' EXIT
tries=0
until curl -fs -m 30 "http://127.0.0.1:$port/ng-probe.php" | grep -q '"plugin":true'; do
    tries=$((tries + 1))
    [ "$tries" -lt 60 ] || { echo "WordPress did not start"; exit 1; }
    sleep 5
done
php tests/integration/check.php "http://127.0.0.1:$port"
