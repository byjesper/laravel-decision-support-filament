# Testing

- Tests use Pest 4 on top of Orchestra Testbench.
- Tag database- or service-bound tests with `->group('integration')`. They are
  excluded from `composer test:unit` and `composer test:parallel`, and run only
  under `composer test:integration`.
- Keep unit tests fast and isolated; they run in parallel.
- `composer test` runs the whole pipeline — guideline check, lint, static
  analysis, type coverage, unit, parallel, integration — and must be green
  before merging.
