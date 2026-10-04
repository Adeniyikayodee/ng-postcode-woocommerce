#!/bin/sh
# Builds the installable plugin zip from the committed tree: scripts/build_zip.sh [ref]
set -eu
cd "$(dirname "$0")/.."
slug=ng-postcode-for-woocommerce
git archive --format=zip --prefix="$slug/" --output="$slug.zip" "${1:-HEAD}"
echo "$slug.zip"
