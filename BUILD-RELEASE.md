# Building Release Distribution Zip

This document explains how to create a clean distribution zip file for WordPress.org submission.

## Problem

WordPress.org does not allow certain files in plugin submissions:
- Shell scripts (*.sh)
- Git files (.git, .gitignore)
- Development files (.DS_Store, .claude)
- Node modules

The `vendor/paragonie/random_compat/build-phar.sh` file specifically causes rejection.

## Pre-Release Checklist

Before creating the distribution zip, ensure:

1. **Version numbers match** in these 3 files:
   - `gcal-tag-filter.php` header comment: `Version: X.X.X`
   - `gcal-tag-filter.php` constant: `define( 'GCAL_TAG_FILTER_VERSION', 'X.X.X' );`
   - `readme.txt` stable tag: `Stable tag: X.X.X`

2. **Changelog updated** in `readme.txt` with the new version

## Solution

### Step 1: Strip development dependencies

The test suite (PHPUnit, Brain Monkey, Mockery, etc.) is declared under
`require-dev` and installed into `vendor/` during development. It must not
ship in the distribution. Regenerate a production-only `vendor/` first:

```bash
composer install --no-dev --optimize-autoloader
```

This removes the dev packages and rewrites the Composer autoloader without
them. To restore the dev tools afterward for local testing, run
`composer install`. The zip command in Step 3 also excludes `tests/`,
`phpunit.xml.dist`, and `.phpunit.result.cache`, which are development-only.

### Step 2: Remove Problematic Files

Before creating the zip, remove the shell script from vendor:

```bash
rm vendor/paragonie/random_compat/build-phar.sh
```

### Step 3: Create Distribution Zip

From the parent directory of the plugin folder:

```bash
cd /Users/samb/GitHub

zip -r gcal-tag-filter-v{VERSION}.zip gcal-tag-filter \
  -x "gcal-tag-filter/.git/*" \
  -x "gcal-tag-filter/.gitignore" \
  -x "gcal-tag-filter/.DS_Store" \
  -x "gcal-tag-filter/admin/.DS_Store" \
  -x "gcal-tag-filter/public/.DS_Store" \
  -x "gcal-tag-filter/.claude/*" \
  -x "gcal-tag-filter/.superpowers/*" \
  -x "gcal-tag-filter/docs/*" \
  -x "gcal-tag-filter/tests/*" \
  -x "gcal-tag-filter/phpunit.xml.dist" \
  -x "gcal-tag-filter/.phpunit.result.cache" \
  -x "gcal-tag-filter/node_modules/*" \
  -x "gcal-tag-filter/*.zip"
```

Replace `{VERSION}` with the actual version number (e.g., `1.0.24`).

### Step 4: Verify the Zip

Check that no shell scripts are included:

```bash
unzip -l gcal-tag-filter-v{VERSION}.zip | grep "\.sh$"
```

This should return no results.

### Step 5: Test Upload

Upload to WordPress.org. If you get an error about unexpected files, check the error message for the specific file path and add it to the exclusion list.

## Quick Command

For convenience, here's the complete command:

```bash
cd /Users/samb/GitHub/gcal-tag-filter && \
composer install --no-dev --optimize-autoloader && \
rm -f vendor/paragonie/random_compat/build-phar.sh && \
cd .. && \
VERSION="1.0.24" && \
zip -r gcal-tag-filter-v${VERSION}.zip gcal-tag-filter \
  -x "gcal-tag-filter/.git/*" \
  -x "gcal-tag-filter/.gitignore" \
  -x "gcal-tag-filter/.DS_Store" \
  -x "gcal-tag-filter/admin/.DS_Store" \
  -x "gcal-tag-filter/public/.DS_Store" \
  -x "gcal-tag-filter/.claude/*" \
  -x "gcal-tag-filter/.superpowers/*" \
  -x "gcal-tag-filter/docs/*" \
  -x "gcal-tag-filter/tests/*" \
  -x "gcal-tag-filter/phpunit.xml.dist" \
  -x "gcal-tag-filter/.phpunit.result.cache" \
  -x "gcal-tag-filter/node_modules/*" \
  -x "gcal-tag-filter/*.zip" && \
echo "✓ Created gcal-tag-filter-v${VERSION}.zip"
```

## Notes

- The build-phar.sh file is from the `paragonie/random_compat` package
- This file is not needed for the plugin to function
- It's safe to delete it before distribution
- After creating the zip, you can restore the file from git if needed: `git checkout vendor/paragonie/random_compat/build-phar.sh`
