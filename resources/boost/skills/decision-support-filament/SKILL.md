---
name: decision-support-filament
description: Wire the byjesper/laravel-decision-support Filament plugin into a panel — register the plugin, publish the bundled mermaid asset, override authorization with a Guide policy, customise navigation/theme via config, subclass the runner to pin one guide in your own navigation, and use the guide resource, tree editor, and runner pages. Use when installing or configuring the editor/runner GUI, not when authoring or running guides in code (see decision-support-development for that).
---

# Decision Support — Filament

The GUI companion to the framework-only decision-support engine. It surfaces the
engine inside a Filament panel: a guide resource, a tree editor for non-developer
authoring with a live Mermaid preview, and an interactive runner. It adds **no**
decision logic — every rule (facts, conditions, validation, the run) lives in the
engine. For authoring guides in code, implementing a `FactProvider`, or running
guides headless, use the **decision-support-development** skill instead.

## When to use this skill

Use this when you are: installing the plugin into a host panel, publishing or
upgrading its asset, restricting access with a policy, customising navigation or
the Mermaid theme, or overriding the editor/runner views.

## 1. Register the plugin

It is a standard Filament plugin (`Filament\Contracts\Plugin`), registered on the
host panel by string — never instantiated globally:

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

This contributes three components:

- **`GuideResource`** — guide CRUD; each guide's versions are managed inline by a
  relation manager that creates draft versions, links to the editor/runner, and
  publishes a draft (running the engine's `GuidePublisher` and surfacing
  validation failures inline).
- **`GuideTreeEditor`** (`/{panel}/guide-tree-editor/{version}`) — node CRUD with
  a per-type config form driven by each node type's `configSchema()`, a structured
  / expression / sentinel edge condition builder fed by the guide's fact
  vocabulary, a live Mermaid preview, and an inline-validating Publish action.
- **`GuideRunner`** (`/{panel}/guide-runner/{version}`) — drives the engine's
  resumable interpreter (question → suspend → `advance()` → verdict) over a
  Mermaid diagram that highlights the reached path. Version-keyed by default;
  a host can subclass it to **pin one guide** and place it in its own navigation
  (see §5).

## 2. Run migrations and publish the asset

```bash
php artisan migrate          # engine tables: guides, versions, nodes, edges
php artisan filament:assets  # copy the bundled mermaid loader to the public path
```

Re-run `filament:assets` after **every package update** and in the deploy
pipeline. The plugin registers one bundled script through `FilamentAsset`; hosts
never add mermaid to their own `package.json`. The checked-in build loads mermaid
from a pinned CDN — run `npm install && npm run build` in the package to vendor a
fully offline bundle.

## 3. Register a fact provider

The editor's condition builder reads the guide's fact **vocabulary**, and the
runner resolves facts at run time — both from the host's `FactProvider`,
registered per guide key on the engine's manager (see
**decision-support-development**):

```php
use ByJesper\DecisionSupport\DecisionSupportManager;

app(DecisionSupportManager::class)
    ->registerProvider('employment-eligibility', EmploymentFactProvider::class);
```

Without a provider a guide can be authored, but its conditions have no facts to
reference and publish validation flags unknown facts.

## 4. Authorization (permissive, host-overridable)

The resource and pages bake in **no** permission strings — they defer to whatever
`Guide` policy the host registers, and are permissive until one exists. Restrict
access by registering a policy against the engine's model:

```php
use ByJesper\DecisionSupport\Models\Guide;
use Illuminate\Support\Facades\Gate;

Gate::policy(Guide::class, GuidePolicy::class);
```

Filament then enforces `viewAny`/`view`/`create`/`update`/`delete` as usual.

## 5. Pin a runner to one guide (host subclass)

The package's `GuideRunner` and `GuideTreeEditor` pages are **extensible** (not
`final`). For a production end-user surface you usually don't want the version-
keyed route — you want one guide, always its currently-active published version,
in your own navigation group, gated by your own rule. Subclass `GuideRunner` and
set `$guideKey`:

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
        return auth()->user()?->can('run employment guide') ?? false; // your own gate
    }
}
```

Register it on the panel like any page: `->pages([EmploymentGuideRunner::class])`.

When `$guideKey` is set the page:

- drops its `{version}` route parameter (route becomes `/{panel}/{slug}`) and
  resolves the guide's `active_version_id` on mount — **404** if the guide is
  unknown or has no published version yet;
- gates by default on the host `Guide` policy's `view` ability (deny when no
  policy is registered). Override `canAccess()` (as above) to gate on a
  permission string instead.

The version-keyed `GuideRunner` reached from the resource's **Run** action is
unaffected and stays permissive. Leave the resource (authoring surface) in its
own navigation group and pin one runner per end-user guide.

## 6. Configuration (optional)

Publish only to change navigation placement or the Mermaid theme:

```bash
php artisan vendor:publish --tag=decision-support-filament-config
```

```php
// config/decision-support-filament.php
'navigation' => ['group' => 'Decision Support', 'sort' => null, 'icon' => 'heroicon-o-rectangle-group', 'label' => null],
'labels' => ['model' => null, 'plural' => null], // override singular/plural model labels (string or translation key)
'forms' => ['layout' => 'page'], // create flow: 'page' | 'modal' | 'slideover' (edit always stays a full page)
'permissions' => ['options' => null], // null => free-form tags; array => constrained multi-select for extra_attributes.permissions
'locales' => [], // e.g. ['da', 'en'] — translation inputs per field in the editor
'fallback_locale' => null, // e.g. 'en' — runner resolves panel locale -> fallback -> base
'mermaid' => ['theme' => 'default'], // forwarded to mermaid.initialize()
```

- `navigation.label` / `labels.*` accept a plain string or a translation key (run through `__()`); `null` keeps Filament's defaults.
- `forms.layout` switches only the **create** flow to a modal/slideover; editing stays a full page because it hosts the versions relation manager.
- `GuideResource` and its List/Create/Edit pages are not `final` — subclass to restyle/relayout.
- The guide `key` is locked after creation and `profile` is locked once a version is published; the guide list has a row `Run` action (disabled until a version is published).
- The tree editor renders native Filament fields with per-field help text from each node type's `configSchema()`.

## 5b. Permissions & multi-language

- **Gating:** a guide's required permissions live at `extra_attributes.permissions` (engine column). The guide form's "Required permissions" field is authoritative; each draft version has an "Edit metadata" action for its working copy (seeded onto the guide at publish). The package enforces nothing — read `$guide->extra_attributes['permissions']` in your host `Guide` policy.
- **i18n:** set `locales`/`fallback_locale`. The editor adds a translation input per locale beside translatable fields (prompt, verdict, text), stored in the node's `*_i18n` maps; blank inputs are dropped. The runner passes `app()->getLocale()` + `fallback_locale` to the engine, which resolves locale → fallback → base.

## 7. Customising views (optional)

```bash
php artisan vendor:publish --tag=decision-support-filament-views
```

Copies the editor/runner Blade views to
`resources/views/vendor/decision-support-filament/`, where they take precedence.
Trade-off: **published views stop receiving upstream changes** — re-check them
after upgrades. Prefer config or a Filament theme for small tweaks.

## Conventions

- Treat this package as wiring only — put decision logic, facts, and side effects
  in the engine (node types are read-only; react to engine events for audit).
- The tree editor edits **draft** versions; publishing freezes them into the
  immutable snapshot the runner reads. Don't bypass the Publish action — it runs
  the validation that keeps a broken graph out of the snapshot.
- A guide reached an `unknown` outcome in the runner means the run hit a safety
  rail (missing fact with no default/`unknown` branch, a cycle, or the step
  budget) — fix the guide or the provider, not this package.
