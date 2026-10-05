#!/bin/sh
# Serves a throwaway WordPress with WooCommerce and this plugin until stopped.
# Needs Node 24. Usage: tests/integration/serve.sh [php-version] [port]
# Set PLUGIN_DIR to serve an unzipped release instead of this checkout.
set -eu
cd "$(dirname "$0")/../.."
# A blueprint's own PHP preference beats the --php flag, so the version goes in the blueprint.
blueprint=tests/integration/.blueprint.json
sed "s/\"steps\"/\"preferredVersions\": { \"php\": \"${1:-8.3}\", \"wp\": \"latest\" }, \"steps\"/" \
    tests/integration/blueprint.json > "$blueprint"
# exec, so that stopping this script stops the server.
exec npx -y @wp-playground/cli@latest server --port="${2:-9412}" --verbosity=quiet --blueprint="$blueprint" \
    --mount "${PLUGIN_DIR:-$PWD}:/wordpress/wp-content/plugins/adeniyikayode-nigerian-postcode-for-woocommerce" \
    --mount "$PWD/tests/integration/probe.php:/wordpress/ng-probe.php" \
    --mount "$PWD/tests/integration/setup.php:/wordpress/ng-setup.php" \
    --mount "$PWD/tests/integration/stub.php:/wordpress/wp-content/mu-plugins/ng-stub.php"
