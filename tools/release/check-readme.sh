#!/usr/bin/env bash
#
# tools/release/check-readme.sh
#
# Keeps readme.txt under 10 KB: WordPress.org may fail to read a bigger
# one. Measures it as the next release will write it, the notes under
# Unreleased as its changelog (sync-changelog.sh), on copies. Run by
# release-it before the release, and by lefthook when readme.txt or
# CHANGELOG.md is committed.

set -euo pipefail

cd "$(dirname "$0")/../.."

limit=10000

copy=$(mktemp -d)
trap 'rm -rf "$copy"' EXIT
mkdir -p "$copy/tools/release"
cp readme.txt CHANGELOG.md "$copy/"
cp tools/release/sync-changelog.sh "$copy/tools/release/"

# The Unreleased notes, when there are some, as the next version.
perl -0pi -e 's/^## Unreleased\n\n(?=- )/## 9.9.9\n\n/m' "$copy/CHANGELOG.md"
bash "$copy/tools/release/sync-changelog.sh"

size=$(wc -c < "$copy/readme.txt")
if [ "$size" -gt "$limit" ]; then
	echo "readme.txt: $size bytes once released, over $limit: WordPress.org may not read it. Shorten it." >&2
	exit 1
fi

echo "readme.txt: $size bytes once released, under $limit."
