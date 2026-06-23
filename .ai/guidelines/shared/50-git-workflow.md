# Git Workflow (Packages)

- `main` must always be releasable; a library has a single line (no maintenance
  branches). See [[60-releases]] for SemVer tagging.
- Prefer issue-backed work — capture intent before larger changes begin.
- Once a package is public, protect `main` and require pull requests for
  external contributions; external PRs must pass CI and include tests for
  behaviour changes.
- Universal git rules (branch prefixes, no `git -C`, full-suite-before-merge,
  no auto-merge, the pure-docs exception) live in [[50-git-hygiene]].
