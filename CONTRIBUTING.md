# Contributing

This package is open source and published on Packagist. The workflow is
intentionally lightweight but strict enough to keep `main` releasable and to
support external contributions.

## Git Strategy

- `main` must always be releasable and is protected.
- All changes — including the maintainer's — go through pull requests that pass
  CI before merging. Use branches for feature work, behavior changes,
  migrations, public API changes, query behavior, or anything downstream depends
  on.
- Prefer issue-backed work. The issue does not need ceremony, but it should
  capture intent before larger changes begin.

## Branch Names

Use short, descriptive prefixes:

- `feat/...` for new capabilities
- `fix/...` for bugs
- `docs/...` for documentation-only changes
- `ci/...` for tooling and workflow changes

## Releases

Releases are tags from `main` using SemVer:

- Patch: bug fixes, docs, and internal cleanup.
- Minor: new features or config options that are backward compatible.
- Major: breaking changes to the public API, config shape, or stored data.

## Contribution Expectations

External pull requests should pass CI before review and should include tests
for behavior changes.
