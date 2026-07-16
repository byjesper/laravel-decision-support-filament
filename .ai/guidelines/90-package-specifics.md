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
