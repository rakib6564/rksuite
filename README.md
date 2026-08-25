# RK Suite

[![CI](https://github.com/rakib6564/rksuite/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/rakib6564/rksuite/actions/workflows/ci.yml)

RK Suite is a bundled WordPress plugin toolkit. The distributable plugin lives in the [`rk-suite/`](rk-suite/) directory.

## Continuous integration

The [`CI` workflow](.github/workflows/ci.yml) runs on pushes to `main`, `master`, and `develop`, and on every pull request. It checks PHP syntax across PHP 7.4–8.3, runs WordPress Coding Standards, and executes the PHPUnit suite across PHP 7.4, 8.1, and 8.2.

## Releases

The [`Release` workflow](.github/workflows/release.yml) runs when a `v*` tag is pushed. It verifies that the tag matches the `Version:` header in `rk-suite/rk-suite.php`, builds a clean `dist/rk-suite.zip` package, and attaches it to an automatically generated GitHub Release.

To create a release, update the plugin version, commit the change, and push a matching tag:

```bash
git commit -am "Release 1.16.7"
git tag v1.16.7
git push origin main --tags
```

The package can also be built locally with:

```bash
bash scripts/build-zip.sh
```

See [`PIPELINE-SETUP.md`](PIPELINE-SETUP.md) for the pipeline operating notes.
