#!/usr/bin/env bash
#
# tools/release/sync-changelog.sh
#
# Writes the changelog of readme.txt from CHANGELOG.md, the only one
# written by hand. Run by bump.sh once the version is set: the newest
# version of Takopic (not the Unreleased notes) becomes a = x.y.z =
# title, its notes one * line each, as WordPress.org reads them. The
# older versions, and those of Squidge, stay in CHANGELOG.md only:
# WordPress.org advises to keep only the current one in readme.txt,
# under 10 KB (see check-readme.sh).

set -euo pipefail

cd "$(dirname "$0")/../.."

perl -0777 -i -e '
	open my $changelog, "<", "CHANGELOG.md" or die "CHANGELOG.md: $!\n";
	my $notes = do { local $/; <$changelog> };
	my $versions = "";
	while ($notes =~ /^## (\S+)[^\n]*\n\n(.*?)(?=^## |\z)/msg) {
		my ($version, $body) = ($1, $2);
		next if $version eq "Unreleased";
		last if $version =~ /^0\./;
		$body =~ s/\n +/ /g;
		$body =~ s/^- /* /mg;
		$body =~ s/\s+\z//;
		$versions = "= $version =\n$body\n\n";
		last;
	}
	my $readme = <>;
	$readme =~ s/(^== Changelog ==\n\n).*?(^The older versions)/$1$versions$2/ms
		or die "readme.txt: no == Changelog == to write.\n";
	print $readme;
' readme.txt
