# Laravel Decision Support — Filament

> Filament tree editor and runner for [`byjesper/laravel-decision-support`](https://packagist.org/packages/byjesper/laravel-decision-support).

This package is the **GUI companion** to the framework-only decision-support
engine. The engine resolves facts, evaluates a guide graph, validates and
publishes drafts, and renders Mermaid — all headless. This package surfaces that
engine inside a Filament panel so a non-developer can author guides in a tree
editor with a live preview, and anyone can walk a guide through an interactive
runner. It adds **no** decision logic of its own; every rule lives in the engine.

## Requirements

- PHP 8.4+
- Laravel (`illuminate/*`) 13+
- Filament 5+
- `byjesper/laravel-decision-support` 0.1+ (installed automatically)

## Installation

```bash
composer require byjesper/laravel-decision-support-filament
```

Both service providers (this package and the engine) are auto-discovered. Then:

```bash
# 1. Run the engine's migrations (guides, versions, nodes, edges).
php artisan migrate

# 2. Copy the bundled Mermaid asset to the public path.
php artisan filament:assets
```

> The engine ships its migrations via the framework's package migration loader,
> so `php artisan migrate` picks them up without publishing. If you prefer to
> own and edit them, publish them first with
> `php artisan vendor:publish --tag=decision-support-migrations`.

## Usage

Register the plugin on your Filament panel, exactly like any other Filament
plugin:

```php
use ByJesper\DecisionSupportFilament\DecisionSupportPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(DecisionSupportPlugin::make());
}
```

This contributes three components to the panel:

- **`GuideResource`** — guide CRUD, with each guide's versions managed inline.
  From a version you jump to the tree editor or the runner, and publish a draft
  (the publish action runs the engine's validation pipeline and surfaces any
  failures inline).
- **`GuideTreeEditor`** — the non-developer authoring surface. Nodes are added
  with a per-type config form driven by each node type's `configSchema()`; edges
  carry a structured (fact + operator + value), expression, or sentinel
  condition built from the guide's fact vocabulary; a **live Mermaid preview**
  re-renders on every change.
- **`GuideRunner`** — walks a guide through the engine's resumable interpreter,
  rendering each question/lookup, driving `advance()`, and showing the verdict
  over a Mermaid diagram that highlights the reached path.

### Register a fact provider

A guide branches on **facts**, which the host resolves through a `FactProvider`
registered against the guide's key. The editor's condition builder reads that
provider's *vocabulary* to offer fact/operator/value choices, and the runner
calls it to resolve facts at run time. Without one, a guide can still be authored
but its conditions have no facts to reference. Register one per guide key in a
service provider's `boot()`:

```php
use ByJesper\DecisionSupport\DecisionSupportManager;

app(DecisionSupportManager::class)
    ->registerProvider('employment-eligibility', EmploymentFactProvider::class);
```

See the engine package for how to implement a `FactProvider`, author guides in
code, and run them headless.

## Configuration

The package works out of the box — publishing config is **optional**, only when
you want to change navigation placement or the Mermaid theme:

```bash
php artisan vendor:publish --tag=decision-support-filament-config
```

```php
// config/decision-support-filament.php
return [
    // Where the GuideResource appears in the panel navigation.
    'navigation' => [
        'group' => 'Decision Support',          // null to ungroup
        'sort' => null,                          // int to order within the group
        'icon' => 'heroicon-o-rectangle-group', // any registered icon
    ],

    // Forwarded to mermaid.initialize(); use any built-in mermaid theme.
    'mermaid' => [
        'theme' => 'default',                    // 'dark', 'forest', 'neutral', …
    ],
];
```

## Customising the views

The editor and runner pages render from package Blade views. Publishing them is
**optional**, only when you need to change their markup or styling:

```bash
php artisan vendor:publish --tag=decision-support-filament-views
```

This copies the views to `resources/views/vendor/decision-support-filament/`,
where they take precedence. Note the trade-off: **published views no longer
receive upstream changes**, so re-check them against the package after upgrades.
For small tweaks, prefer the config above or a Filament theme.

## Front-end asset

The plugin registers a single bundled script through Filament's asset manager,
so you never have to add Mermaid to your own `package.json`. It finds every
preview container, renders the Mermaid source, and re-renders after each Livewire
DOM update (editor edits, runner advances).

Run `php artisan filament:assets` after installing **and after each package
update** (and as part of your deploy pipeline) to keep the published asset in
sync. The checked-in build loads Mermaid from a pinned CDN; to vendor a fully
offline bundle instead, run `npm install && npm run build` in the package.

## Authorization

The resource and pages are **permissive by default** and defer to whatever
`Guide` policy the host registers — so the package bakes in no permission
strings. Restrict access by registering a policy against the engine's model:

```php
use ByJesper\DecisionSupport\Models\Guide;
use Illuminate\Support\Facades\Gate;

Gate::policy(Guide::class, GuidePolicy::class);
```

Once a policy exists, Filament enforces its `viewAny`/`view`/`create`/`update`/
`delete` methods on the resource as usual.

## Testing

```bash
composer test
```

This runs the guideline check, lint (Rector + Pint), static analysis (Larastan
level 8), 100% type coverage, and the unit + integration suites. The Filament
pages are exercised as Livewire components against a test panel; those tests are
tagged `->group('integration')` and run under `composer test:integration`.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
