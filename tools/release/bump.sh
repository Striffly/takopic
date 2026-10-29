#!/usr/bin/env bash
#
# tools/release/bump.sh <version>
#
# Writes the version of a release where release-it does not. Run by
# release-it once it has set package.json and the header of the plugin
# (@release-it/bumper), before its commit:
#
# - the Stable tag of readme.txt;
# - the Unreleased title of CHANGELOG.md, dated, with an empty one above
#   it for the next notes, as Keep a Changelog does, then the changelog of
#   readme.txt from it (sync-changelog.sh).

set -euo pipefail

cd "$(dirname "$0")/../.."

version=${1:?Usage: bump.sh <version>}

perl -pi -e "s/^Stable tag:.*/Stable tag: $version/" readme.txt

perl -pi -e "s/^## Unreleased\$/## Unreleased\n\n## $version – $(date +%F)/" CHANGELOG.md
bash tools/release/sync-changelog.sh
