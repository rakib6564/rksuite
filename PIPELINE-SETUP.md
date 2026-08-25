# RK Suite — GitHub CI/CD Pipeline

Drop these files into the **root of your plugin's GitHub repo** (the folder that
contains `rk-suite.php`). Commit and push — that's it.

```
your-repo/
├─ rk-suite.php
├─ .github/workflows/ci.yml        ← lint + WPCS + PHPUnit on every push/PR
├─ .github/workflows/release.yml   ← builds & publishes the plugin zip on a version tag
├─ phpcs.xml.dist                  ← WordPress Coding Standards ruleset
├─ .distignore                     ← what to exclude from the release zip
└─ scripts/build-zip.sh            ← clean-zip builder
```

## What runs

**CI** (`ci.yml`) — on push to main/master/develop and every PR:
- **PHP Lint** — `php -l` across PHP 7.4 → 8.3
- **WPCS** — WordPress Coding Standards via PHPCS (results annotate the PR)
- **PHPUnit** — your `tests/` suite on PHP 7.4 / 8.1 / 8.2 (`composer test`)

**Release** (`release.yml`) — when you push a tag like `v1.16.6`:
1. Checks the tag matches the `Version:` header in `rk-suite.php` (fails if not)
2. Builds a clean `rk-suite.zip` (excludes tests, .github, composer, etc.)
3. Creates a GitHub Release with the zip attached + auto-generated notes

## How to cut a release

```bash
# 1. bump the Version: header in rk-suite.php (e.g. 1.16.7) and RK_SUITE_VERSION
# 2. commit
git commit -am "Release 1.16.7"
# 3. tag + push
git tag v1.16.7
git push origin main --tags
```

The Release workflow builds and publishes `rk-suite.zip` automatically.

## Build locally (optional)

```bash
bash scripts/build-zip.sh   # → dist/rk-suite.zip
```

## Notes
- No secrets needed — releases use the built-in `GITHUB_TOKEN`.
- If your repo puts the plugin in a subfolder, run the workflows from that folder
  (add `defaults.run.working-directory:` or move the files into that subfolder).
- Add a WordPress.org deploy step later with `10up/action-wordpress-plugin-deploy`
  if you ever publish to the .org directory.
