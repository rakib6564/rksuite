# RK Suite

[![CI](https://github.com/rakib6564/rksuite/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/rakib6564/rksuite/actions/workflows/ci.yml)

RK Suite is a bundled WordPress plugin toolkit. The distributable plugin lives in the [`rk-suite/`](rk-suite/) directory.

## Continuous integration

The [`CI` workflow](.github/workflows/ci.yml) runs on pushes to `main`, `master`, and `develop`, and on every pull request. It checks PHP syntax across PHP 7.4–8.3, runs WordPress Coding Standards, and executes the PHPUnit suite across PHP 7.4, 8.1, and 8.2.

## Releases

The [`Release` workflow](.github/workflows/release.yml) uses [Release Please](https://github.com/googleapis/release-please) to interpret Conventional Commits, open a release pull request, update the semantic version in `rk-suite/rk-suite.php`, and maintain [`CHANGELOG.md`](CHANGELOG.md). Merging that release pull request updates the version and changelog on `main`. Pushing the generated `v*` tag then verifies the plugin version, builds `dist/rk-suite.zip`, and attaches it to a GitHub Release.

Use Conventional Commit prefixes such as `feat:`, `fix:`, and `BREAKING CHANGE:` to communicate the intended semantic version bump. After the release pull request is merged, the workflow handles the tag, changelog, and release artifact automatically.

The package can also be built locally with:

```bash
bash scripts/build-zip.sh
```

See [`PIPELINE-SETUP.md`](PIPELINE-SETUP.md) for pipeline operating notes and [`CHANGELOG.md`](CHANGELOG.md) for the generated release history.
