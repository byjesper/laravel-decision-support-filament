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
