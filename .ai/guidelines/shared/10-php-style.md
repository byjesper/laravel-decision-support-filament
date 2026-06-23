# PHP Style

- Target PHP 8.4. Begin every PHP file with `declare(strict_types=1);`.
- Type everything: parameters, return types, and properties. The suite enforces
  100% type coverage (`composer test:type:coverage`), so untyped code fails CI.
- Use constructor property promotion and `readonly` where applicable.
- Add `#[\Override]` to methods that override a parent or implement an interface.
- Formatting is owned by Laravel Pint — never hand-format. Run `composer lint`.
- Rector handles automated upgrades; keep its dry run clean
  (`composer test:lint` includes `rector --dry-run`).
