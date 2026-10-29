# Contributing to Takopic

Thanks for helping! Bugs and ideas go to the [issues](https://github.com/Striffly/takopic/issues),
changes to a pull request.

## Conventions

### Commits

- In English, the subject starting with a [gitmoji](https://gitmoji.dev/) shortcode, then a verb in the imperative:
  `:sparkles: Roll the counts during a job`.
- A description under the subject, after a blank line: what changes and why, in a few sentences.

### Changes

- With each change a user would notice, a note under `## Unreleased` in `CHANGELOG.md`: the release copies the notes
  to `readme.txt`.
- `README.md`, and `readme.txt` where it says the same, follow what they describe.

## Branches

- `main`: the work in progress. Every change lands there, and each release is a tag on it.
- `hotfix/x.y.z`: an urgent fix of the last release, when `main` holds changes not ready to go: from the tag of the
  release (`git switch -c hotfix/1.0.1 1.0.0`), released the same way, then merged into `main`. Only for the last
  release: WordPress.org serves a single version.

## Tests

The integration tests run on the test suite of WordPress: they upload images and check the files on the disk.

Needed:

- The [libraries](README.md#-requirements): a test needing one that is not installed is skipped.
- A MySQL or MariaDB database of their own.

> [!WARNING]
> The tests empty their database at each run: never give them the one of a site.

```bash
# PHPUnit, the WordPress test library and WordPress itself.
composer install

# The database, by default wordpress_test on 127.0.0.1 as root without password.
export WP_TESTS_DB_NAME=wordpress_test WP_TESTS_DB_USER=root \
	WP_TESTS_DB_PASSWORD=root WP_TESTS_DB_HOST=127.0.0.1:3306

composer test             # On a single site.
composer test:multisite   # On a network.
```

The GitHub workflow runs them on PHP 8.2 and 8.4, on a single site and on a network, at each push and before each
release.

## Logo and banner

The assets of the page of Takopic on WordPress.org are in `.wordpress-org/`.

```bash
# The logo and the banners, drawn from the pixel grids of tools/brand/sprites/,
# in assets/images/ and .wordpress-org/.
bun run brand

# The screenshots, in the order of readme.txt, 1408x880 at 2x, with the Chromium of the system
# (--chrome sets another). On a test site with Takopic active, its media library optimised and the
# WebP and AVIF served by the rewrite rules: the second screen optimises every image again.
bun run screenshots -- --url=http://localhost:8086 --user=bench --password=bench
```

## Release

Needed: [Composer](https://getcomposer.org/), [Bun](https://bun.sh/), [Node.js](https://nodejs.org/) 22 or later and
`zip`.

Write the notes under `Unreleased` in `CHANGELOG.md` as the changes come: the release writes them in `readme.txt`.
When a new major WordPress is out, have CI test it with
`composer update roots/wordpress-no-content wp-phpunit/wp-phpunit`, then update `Tested up to:` in `readme.txt`. Then,
on `main` or a hotfix branch, once CI passed on it:

```bash
bun run release:dry    # What it would do.
bun run release        # Patch, minor or major.
bun run release 1.2.0  # A given version, or 1.2.0-beta.1 for a pre-release.
```

release-it then:

1. checks CI, the notes and `Tested up to:`;
2. sets the version everywhere (never by hand), dates the notes, copies them to `readme.txt` and opens an empty
   `Unreleased` above them for the next ones;
3. commits `:bookmark: Release x.y.z`, tags it and pushes.

The tag runs CI again, then publishes the zip on GitHub and WordPress.org.

To build the zip only: `bun install && bun run release:zip`.
