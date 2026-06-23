# Releases (Packages)

- Releases are SemVer tags cut from `main`.
  - Patch: bug fixes, docs, internal cleanup.
  - Minor: new features or config options that are backward compatible.
  - Major: breaking changes to the public API, config shape, or stored data.
- Consumers should depend on tagged releases. Temporary branch constraints are
  acceptable only during active co-development and must be replaced with a tag
  promptly.
- Record every change under `## [Unreleased]` in `CHANGELOG.md`; move it under a
  version heading at release time.
