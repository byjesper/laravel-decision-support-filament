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
