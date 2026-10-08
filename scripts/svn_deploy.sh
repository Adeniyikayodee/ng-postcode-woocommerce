#!/bin/sh
# Publishes the built zip to WordPress.org as trunk and a tag: scripts/svn_deploy.sh <version>
# Needs SVN_PASSWORD. A version that is already tagged is refused.
set -eu
cd "$(dirname "$0")/.."
slug=adeniyikayode-nigerian-postcode-for-woocommerce
version=$1
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
unzip -q "$slug.zip" -d "$work"
svn checkout -q --depth immediates "https://plugins.svn.wordpress.org/$slug" "$work/svn"
cd "$work/svn"
svn update -q --set-depth infinity trunk
rsync -a --delete "$work/$slug/" trunk/
svn add -q --force trunk
svn status trunk | sed -n 's/^! *//p' | while read -r gone; do svn delete -q "$gone"; done
svn copy -q trunk "tags/$version"
printf %s "$SVN_PASSWORD" | svn commit -q -m "chore: release $version" \
    --username adeniyikayode --password-from-stdin --non-interactive --no-auth-cache
