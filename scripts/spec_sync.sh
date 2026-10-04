#!/bin/sh
# Compare spec/ with upstream. With no argument, against the commit in spec/SOURCE,
# which proves the copy is untouched. With "main", against upstream's main, which
# shows whether upstream has moved on. With "main --write", take upstream's copy.
set -eu
cd "$(dirname "$0")/../spec"
repo=https://raw.githubusercontent.com/Adeniyikayodee/ng-postcode
ref=${1:-$(tail -n 1 SOURCE)}
status=0
for name in vectors requests responses tolerance; do
    curl -fsSL "$repo/$ref/spec/$name.json" -o "$name.upstream"
    if ! cmp -s "$name.json" "$name.upstream"; then
        echo "spec/$name.json differs from upstream at $ref"
        status=1
        [ "${2:-}" = "--write" ] && cp "$name.upstream" "$name.json"
    fi
    rm "$name.upstream"
done
if [ "${2:-}" = "--write" ]; then
    sha=$(git ls-remote https://github.com/Adeniyikayodee/ng-postcode.git "refs/heads/$ref" | cut -f1)
    sed -i.bak "\$s/.*/$sha/" SOURCE && rm SOURCE.bak
    status=0
fi
exit $status
