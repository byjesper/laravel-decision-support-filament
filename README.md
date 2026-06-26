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

### Getting the engine's Boost skill

The engine package `byjesper/laravel-decision-support` ships a
[Laravel Boost](https://laravel.com/docs/boost) skill
(`decision-support-development`) that helps an AI agent author guides, fact
providers, node types, and conditions. **Boost only publishes skills from
packages that are _direct_ dependencies in your root `composer.json`** — it reads
`require`/`require-dev` and does not walk transitive dependencies. Because the
engine is installed transitively via this package, `boost:install`/`boost:update`
never see its skill on their own.

Since you use the engine's API directly anyway, require it directly and discover
its skill:

```bash
composer require byjesper/laravel-decision-support
php artisan boost:update --discover   # select the engine to publish its skill
```

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
  From a version you jump to the tree editor or the runner, publish a draft (the
  publish action runs the engine's validation pipeline and surfaces any failures
  inline), or spin up a **new version from an existing one** (clones its nodes,
  edges and metadata into a fresh draft).
- **`GuideTreeEditor`** — the non-developer authoring surface, built as a native
  Filament form: nodes and edges are **collapsible repeaters** (add, reorder,
  edit in place, delete) with a per-type config form driven by each node type's
  `configSchema()`; edges carry a structured (fact + operator + value),
  expression, or sentinel condition from the guide's fact vocabulary (and cannot
  loop a node to itself). A **live Mermaid preview** and a **live Validation
  panel** (the engine's publish checks run against your current edits) update as
  you type. Header actions **Save draft**, **Test guide**, and **Publish version**;
  a per-version **Metadata** section edits the consumer metadata. Once a version is
  **published**, its structure is locked (an info callout explains why), but
  display content — labels, prompts, verdicts, warnings and their translations —
  and metadata stay editable, so copy fixes don't need a new version.
- **`GuideRunner`** — walks a guide through the engine's resumable interpreter,
  rendering each question/lookup, driving `advance()`, with a **Back** button to
  step to the previous answer, and showing the verdict over a Mermaid diagram
  that highlights the reached path. Outcome text (and question prompts) render as
  **Markdown**, so authors can write a scannable "what to do" list; raw HTML in
  the content is escaped, and plain text is unaffected. Version-keyed by default;
  [pin it to one guide](#pinning-a-runner-to-one-guide) for an end-user surface.

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

## Pinning a runner to one guide

The `GuideRunner` and `GuideTreeEditor` pages are **extensible** (not `final`).
The default `GuideRunner` is version-keyed — its route carries a `{version}`, so
the resource's **Run** action can run any draft or published version. For a
production end-user surface you instead want **one** guide, always its
currently-active published version, in your own navigation, gated by your own
rule. Subclass `GuideRunner` and set `$guideKey`:

```php
use ByJesper\DecisionSupportFilament\Pages\GuideRunner;

class EmploymentGuideRunner extends GuideRunner
{
    protected static ?string $guideKey = 'employment-eligibility'; // pins the guide
    protected static bool $shouldRegisterNavigation = true;
    protected static string | \UnitEnum | null $navigationGroup = 'HR';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-identification';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('run employment guide') ?? false;
    }
}
```

Register it on the panel like any page (`->pages([EmploymentGuideRunner::class])`).
When `$guideKey` is set the page:

- **drops the `{version}` route parameter** (its route becomes `/{panel}/{slug}`)
  and resolves the guide's `active_version_id` on mount — **404** if the guide is
  unknown or has no published version yet;
- **authorizes through the host `Guide` policy's `view` ability by default**
  (denying when no policy is registered). Override `canAccess()`, as above, to
  gate on a permission string instead.

The version-keyed `GuideRunner` reached from the resource is unaffected and stays
permissive. Keep the authoring resource in its own navigation group and pin one
runner per end-user guide.

## Configuration

The package works out of the box — publishing config is **optional**, only when
you want to change navigation, labels, the create-form layout, or the Mermaid
theme:

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
        'label' => null,                         // null => "Guides"; string or translation key
    ],

    // Override the singular/plural model labels (titles, breadcrumbs, buttons).
    // null => Filament's defaults. Strings may be plain text or translation keys.
    'labels' => [
        'model' => null,                         // e.g. 'Decision guide'
        'plural' => null,                        // e.g. 'Decision guides'
    ],

    // How a guide is CREATED from the list: 'page' (default), 'modal', or
    // 'slideover'. Editing always stays a full page — it hosts the versions.
    'forms' => [
        'layout' => 'page',
    ],

    // The catalog of permissions an author can pick from (chosen ones stored on
    // the guide at extra_attributes.permissions):
    //   null      => no catalog; the field becomes a warning callout (you can't
    //                gate by permission until you supply one),
    //   array     => a searchable multi-select with that catalog for every guide,
    //   closure   => fn (?Guide $guide): array, resolved per guide so different
    //                guides offer different catalogs.
    // 'mode' is the default for how the chosen permissions combine — 'any' (OR)
    // or 'all' (AND); authors override it per guide (extra_attributes.permissions_mode).
    'permissions' => [
        'options' => null,
        'mode' => 'any',
    ],

    // Multi-language content. Locales the tree editor offers a translation input
    // for (per translatable field). Empty => single language. The runner renders
    // in the panel's active locale, falling back to fallback_locale then base.
    'locales' => [],                             // e.g. ['da', 'en']
    'fallback_locale' => null,                   // e.g. 'en'

    // Forwarded to mermaid.initialize(); use any built-in mermaid theme.
    'mermaid' => [
        'theme' => 'default',                    // 'dark', 'forest', 'neutral', …
    ],
];
```

A few behaviours worth knowing:

- **`forms.layout` controls the _create_ flow only.** `'modal'`/`'slideover'`
  open the create form from the guide list without leaving it. **Editing always
  stays a full page**, because that page hosts each guide's versions (the
  relation manager, with Edit-tree / Run / Publish) — and those only render on a
  record page. The modal covers guide *identity* (key/name/description/profile);
  tree editing still happens on the `GuideTreeEditor` reached from a version.
- **The guide `key` is locked after creation** (it is the stable identifier a
  fact provider binds to) and **`profile` is locked once a version is published**
  (changing it could invalidate the live tree).
- **The guide list has a row `Run` action** that opens the guide's currently
  active published version in the runner; it is disabled for guides that have no
  published version yet.
- The `GuideResource` and its `List`/`Create`/`Edit` pages are **not `final`**, so
  a host can subclass them to restyle or relayout without forking.
- **The tree editor renders native Filament fields** with per-field help text
  (from each node type's `configSchema()`), so the authoring form follows your
  panel's theme and spacing.

### Permissions / access gating

A guide carries consumer-defined `extra_attributes` (see the engine README) —
the headline use is the permissions required to see or run it. The guide form has
a **Required permissions** field and a **Permission match** mode — *any* of the
permissions (OR) or *all* of them (AND) — and each draft version has an **Edit
metadata** action for its own working copy (which seeds the guide on publish).

Set `permissions.options` to your permission **catalog** — an array (one catalog
for every guide) or a closure `fn (?Guide $guide): array` (resolved per guide) —
to render a searchable multi-select. Leave it `null` and the field becomes a
warning callout explaining that permissions can't be gated until a catalog is
configured (a guide that already carries permissions still shows a removable
multi-select so they can be cleared). Chosen permissions are stored at
`extra_attributes.permissions`, and the match mode shows whenever a catalog
exists or the guide has permissions.
The **guide-level copy is authoritative** for gating and a direct edit takes
effect immediately. The match mode is stored at `extra_attributes.permissions_mode`
and defaults to the configured `permissions.mode` (ships as `any`/OR).

The package **enforces nothing** — wire both to your own `Guide` policy:

```php
public function view(User $user, Guide $guide): bool
{
    $required = $guide->extra_attributes['permissions'] ?? [];
    $mode     = $guide->extra_attributes['permissions_mode']
        ?? config('decision-support-filament.permissions.mode', 'any');

    $held = collect($required)->filter(fn (string $p): bool => $user->can($p));

    return $required === []
        || ($mode === 'all' ? $held->count() === count($required) : $held->isNotEmpty());
}
```

The resource, the list **Start** action, and a pinned `GuideRunner` all defer to
this policy.

### Required (mandatory) questions

A free (text/date/number) question can be marked **Required** in the tree editor.
The runner flags its prompt with a red asterisk and, on a blank submit, shows an
inline validation error instead of advancing — the Submit button stays enabled.
The flag is stored at the node's `config.required`; the engine re-suspends on a
blank answer as the authoritative backstop (see the engine README).

### Multi-language content

Set `locales` (and optionally `fallback_locale`) to author per-locale content.
The tree editor then shows a translation input per locale beside each
translatable field — question prompt, outcome verdict/text, and each node's
**display label** — writing into the node's `*_i18n` maps. The runner renders in
the panel's active locale (`app()->getLocale()`), falling back to
`fallback_locale` and then each field's base string — so a guide with no
translations behaves exactly as before.

The **path/preview diagram is localized too**: node labels, prompts and verdicts
render in the panel locale. Giving a fact or decision node a label (and per-locale
labels) replaces its raw key in the diagram with readable, translated text. **Edges**
take a label + per-locale inputs as well, so a branch can read "Long tenure"
instead of the derived `tenure >= 5`.

The tree editor's **live validation panel** is localized through `validation.{code}`
translations (shipped for `en` and `da`); any unmapped code falls back to the
engine's English message.

### Translating the UI chrome

All of the package's own UI strings (section headings, action labels, field
labels, notices) are translatable. **English (`en`) and Danish (`da`) ship in the
box** — set the panel's locale to `da` and the chrome renders in Danish with no
further setup.

To adjust the bundled wording or add another language, publish the language files:

```bash
php artisan vendor:publish --tag=decision-support-filament-translations
```

This copies `en` and `da` to `lang/vendor/decision-support-filament/`; edit them in
place, or copy a folder to a new locale (e.g. `…/de/`) and translate. The chrome
then renders in the panel's active locale alongside your translated guide content.

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
so you never have to add Mermaid to your own `package.json`. **Mermaid is bundled
into the asset** (no runtime CDN fetch), so previews render fast and work offline.
It finds every preview container, renders the Mermaid source, and re-renders after
each Livewire DOM update — coalesced per frame and skipping unchanged diagrams.

After installing **and after each package update** (and in your deploy pipeline),
re-publish the asset and clear caches so new markup/views take effect:

```bash
php artisan filament:assets
php artisan view:clear
php artisan cache:clear
```

To rebuild the bundle from source, run `npm install && npm run build` in the
package.

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
`delete` methods on the resource as usual. A
[pinned runner](#pinning-a-runner-to-one-guide) authorizes on the policy's
`view` ability by default, or override its `canAccess()` for a permission string.

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
