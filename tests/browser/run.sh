#!/bin/sh
# Boots the throwaway store and runs the browser tests against it.
# Needs Node 24 and `npx playwright install chromium`. Usage: tests/browser/run.sh [php-version]
set -eu
cd "$(dirname "$0")/../.."
port=9418
sh tests/integration/serve.sh "${1:-8.3}" "$port" >/dev/null 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null' EXIT
tries=0
until curl -fs -m 30 "http://127.0.0.1:$port/ng-probe.php" | grep -q '"plugin":true'; do
    tries=$((tries + 1))
    [ "$tries" -lt 60 ] || { echo "WordPress did not start"; exit 1; }
    sleep 5
done
BASE_URL="http://127.0.0.1:$port" npx playwright test
