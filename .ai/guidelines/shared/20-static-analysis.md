# Static Analysis

- PHPStan (via Larastan) runs at level 8 with zero errors
  (`composer test:type:check`).
- Do not add baseline entries to silence new errors — fix the underlying type
  issue. `phpstan-baseline.neon` is for legacy debt only and should shrink.
- Prefer precise array shapes and generics in PHPDoc over `mixed`/`array`.
