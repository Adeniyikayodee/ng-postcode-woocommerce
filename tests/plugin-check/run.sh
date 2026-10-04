#!/bin/sh
# Builds the release zip, installs it in a throwaway WordPress, and runs WordPress.org's
# Plugin Check against it. Fails on any finding. Needs Node 24.
set -eu
cd "$(dirname "$0")/../.."
work=$(mktemp -d)
port=9422
unzip -q "$(sh scripts/build_zip.sh)" -d "$work"
npx -y @wp-playground/cli@latest server --port="$port" --verbosity=quiet \
    --blueprint=tests/plugin-check/blueprint.json \
    --mount "$work/ng-postcode-for-woocommerce:/wordpress/wp-content/plugins/ng-postcode-for-woocommerce" \
    --mount "$PWD/tests/plugin-check/check.php:/wordpress/ng-plugin-check.php" >/dev/null 2>&1 &
server=$!
# npx leaves its server running when it is killed, so stop that by its port as well.
trap 'kill "$server" 2>/dev/null; pkill -f -- "--port=$port" 2>/dev/null; rm -rf "$work"' EXIT
tries=0
until curl -fs -m 300 "http://127.0.0.1:$port/ng-plugin-check.php" -o "$work/findings.json" && grep -q '"checks"' "$work/findings.json"; do
    tries=$((tries + 1))
    [ "$tries" -lt 60 ] || { echo "WordPress did not start"; exit 1; }
    sleep 5
done
cat "$work/findings.json"; echo
grep -q '"findings":\[\]' "$work/findings.json"
