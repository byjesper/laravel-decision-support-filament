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

- **A pushed tag is not a release — create the GitHub Release too.** This repo
  publishes a GitHub Release for every version, but `git push origin vX.Y.Z` only
  creates the tag; the Release object is separate. After the tag is pushed and CI
  is green, run `gh release create vX.Y.Z --title vX.Y.Z --verify-tag --notes …`
  (notes drawn from that version's CHANGELOG section, ending in a compare link) so
  it appears on the Releases page and becomes "Latest". Releasing isn't done until
  the Release exists.
