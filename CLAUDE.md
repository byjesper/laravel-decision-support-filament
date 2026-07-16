<!--
  GENERATED FILE — DO NOT EDIT.
  Composed from .ai/guidelines/ by `composer guidelines:build`.
  Shared fragments live in .ai/guidelines/shared/ (managed by `guidelines:sync`).
-->

# PHP Style

- Target PHP 8.4. Begin every PHP file with `declare(strict_types=1);`.
- Type everything: parameters, return types, and properties. The suite enforces
  100% type coverage (`composer test:type:coverage`), so untyped code fails CI.
- Use constructor property promotion and `readonly` where applicable.
- Add `#[\Override]` to methods that override a parent or implement an interface.
- Formatting is owned by Laravel Pint — never hand-format. Run `composer lint`.
- Rector handles automated upgrades; keep its dry run clean
  (`composer test:lint` includes `rector --dry-run`).

# Static Analysis

- PHPStan (via Larastan) runs at level 8 with zero errors
  (`composer test:type:check`).
- Do not add baseline entries to silence new errors — fix the underlying type
  issue. `phpstan-baseline.neon` is for legacy debt only and should shrink.
- Prefer precise array shapes and generics in PHPDoc over `mixed`/`array`.

# Testing

- Tests use Pest 4 on top of Orchestra Testbench.
- Tag database- or service-bound tests with `->group('integration')`. They are
  excluded from `composer test:unit` and `composer test:parallel`, and run only
  under `composer test:integration`.
- Keep unit tests fast and isolated; they run in parallel.
- `composer test` runs the whole pipeline — guideline check, lint, static
  analysis, type coverage, unit, parallel, integration — and must be green
  before merging.

# Tagging & Release Immutability

- **Tags are immutable.** Never move, delete-and-recreate, or force-push a tag
  that already exists — especially one a registry has already seen. Packagist
  treats a published version as permanent and will reject a re-pushed/moved tag
  (a supply-chain safeguard: it stops a tag's contents being swapped after
  publication). Assume "published once = frozen forever".
- **Made a mistake in a release? Fix forward.** Commit the fix and tag the *next*
  version (a patch bump for a fix) — never re-tag the same number. A botched
  `v2.1.0` is corrected by `v2.1.1`, not by re-pushing `v2.1.0`.
- **Tag once, from `main`, after CI is green.** Push the tag exactly once.
- **Need to iterate before a final release?** Use a new pre-release identifier
  (`v2.1.0-rc.1`, `-rc.2`, …), not a moved tag.

# Git Hygiene

These rules are universal — they hold for every repo regardless of its
*branching/release model*, which is project-specific (each project declares it
at the top of its own Git section). See also [[40-tagging]].

- **Branch naming:** short prefixes — `feat/`, `fix/`, `docs/`, `chore/` (plus
  project-specific ones such as `ci/` or `hotfix/` where a project defines
  them). Keep branches focused and short-lived.
- **Never use `git -C <path>`.** Run git from the working directory itself.
- **Verify the full test suite before merging**, not just the files you touched
  — some failures only surface when the whole suite loads together (the way CI
  loads it).
- **Never enable PR auto-merge** (`--auto`) on an unprotected free-plan private
  repo: it merges the moment the PR is mergeable — even while CI is still
  running, or after it has gone red. Wait for checks to finish green (poll
  `gh pr checks <n>`), then merge manually.
- **Pure-documentation exception:** a change whose entire diff is prose (`*.md`,
  ADRs, `.ai/guidelines/` sources, generated `CLAUDE.md`/`AGENTS.md`) needs no
  branch of its own — commit it on the branch you are already on. It stops being
  "pure docs" the moment the diff touches code, config, migrations, tests, or
  dependency manifests.

# Git Workflow (Packages)

- `main` must always be releasable; a library has a single line (no maintenance
  branches). See [[60-releases]] for SemVer tagging.
- Prefer issue-backed work — capture intent before larger changes begin.
- Once a package is public, protect `main` and require pull requests for
  external contributions; external PRs must pass CI and include tests for
  behaviour changes.
- Universal git rules (branch prefixes, no `git -C`, full-suite-before-merge,
  no auto-merge, the pure-docs exception) live in [[50-git-hygiene]].

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

# Package Structure

- A package is a library, not an application: no `App\` namespace, no app
  bootstrap. Tests boot a minimal app via Testbench.
- Register bindings in the service provider's `register()`; wire publishing,
  commands, migrations, and routes in `boot()`, guarding console-only work with
  `runningInConsole()`.
- Ship runtime files only. The `.gitattributes` `export-ignore` rules keep
  tests, CI, and tooling out of the dist tarball.
- Do not commit `composer.lock` — it is git-ignored for libraries.
- Guidelines meant for *consumers* of the package belong in `resources/boost/`
  (Boost auto-discovers them), not `.ai/guidelines/`, which is local to
  developing the package itself.

# Package Specifics

- **Boost skills ship to consumers — keep them in lockstep with every release.**
  This package publishes agent skills from `resources/boost/skills/` (Laravel
  Boost auto-discovers them in the consumer app). They are part of the released
  tarball, so a stale `SKILL.md` misinforms every consumer until the next tag —
  and tags are immutable, so a skill fix that misses a release only reaches
  consumers in a follow-up patch. **Before cutting any release, re-read each
  `SKILL.md` against that version's changes and update anything the change made
  wrong, incomplete, or newly worth documenting** (new/changed public API,
  behaviour changes, renamed config). Treat the skills as release deliverables,
  not docs to backfill later.
