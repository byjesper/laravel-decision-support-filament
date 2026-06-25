# Changelog

All notable changes to `byjesper/laravel-decision-support-filament` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.6.0] - 2026-06-25

### Added

- **Danish (`da`) UI translations now ship in the package.** Set the panel's
  locale to `da` and the runner/editor/resource/versions chrome renders in Danish
  with no `vendor:publish` step. Publishing the translations now copies both `en`
  and `da` for editing or as a base for further locales.
- **The path/preview diagram now renders in the panel locale.** The runner and
  tree-editor Mermaid diagrams pass the active locale (and configured fallback) to
  the engine renderer, so node labels, question prompts and outcome verdicts read
  in the same language as the rest of the run — including the always-visible
  pre-start diagram. (Requires the engine's locale-aware `MermaidRenderer`.)
- **Per-locale node labels in the tree editor.** Each node gains translation
  inputs for its display label (written to `config.label_i18n`), so a fact or
  decision node can show a friendly, localized label in the diagram instead of its
  raw key.
- The **node-type** and **input-type** dropdowns are now translatable
  (`editor.node_type.*`, `editor.input_type.*`, shipped for `en` and `da`),
  falling back to the raw key for host-registered custom node types.
- **Custom / localized edge labels.** Each edge in the tree editor gains a label
  field plus per-locale inputs; the diagram shows that text on the branch instead
  of the derived condition (e.g. a humanised "Long tenure" rather than
  `tenure >= 5`). Persisted on the new engine `guide_edges.label`/`label_i18n`
  columns.
- **The live validation panel is now localized.** Engine validation issues render
  through `validation.{code}` translations (with the engine's structured params),
  shipped for `en` and `da`, falling back to the engine's English message for any
  unmapped (e.g. host-custom) code. (Requires the engine's `ValidationError`
  params.)
- The resource's **navigation group** config now accepts a translation key (run
  through `__()`), like the navigation label and model labels already did.

### Changed

- Requires `byjesper/laravel-decision-support` **^0.3** (locale-aware
  `MermaidRenderer`, edge `label`/`label_i18n` columns, and structured
  `ValidationError` params).

### Fixed

- The **runner** breadcrumb now Title-cases the guides label (e.g. "Guides"),
  matching Filament's resource breadcrumbs and the tree editor (was lower-case
  "guides"). The editor was fixed in 0.5.0; the runner used the same lowercase
  plural model label.

## [0.5.0] - 2026-06-25

### Added

- Outcome text (and question prompts) in the runner now render as **Markdown**,
  so authors can present a scannable "what to do" list (headings, bullets,
  emphasis) instead of one dense paragraph. Raw HTML in the content is escaped and
  unsafe links are stripped; plain text is unaffected (backward compatible).
- Declared `league/commonmark` as a direct dependency (used for the Markdown
  rendering above).
- **All UI strings are now translatable** via a publishable `decision-support-filament`
  translation namespace (runner, editor, resource, versions). Publish them with
  `php artisan vendor:publish --tag=decision-support-filament-translations` and add
  a locale (e.g. `lang/vendor/decision-support-filament/da/…`) to localize the
  panel chrome.
- **New version from this** action on the versions table — clones an existing
  version's nodes, edges and metadata into a fresh editable draft.
- Published versions are now partially editable: their **structure** (keys, types,
  facts, input types, option values, edges, and adding/removing/reordering) is
  locked behind an info callout, but **display content** (labels, prompts,
  verdicts, warnings and their translations) and metadata stay editable — so
  copy/translation fixes no longer require cutting a new version. Content edits
  update nodes in place, keeping edges wired.
- The runner's **Submit** button now shows a spinner and disables its input while
  a step is processing (e.g. a slow external fact lookup), so the wait reads as
  progress instead of a frozen form.

### Changed

- Renamed the run actions for clarity: the guide-list/version **Run** action is
  now **Start**, and the tree editor's **Test run** is now **Test guide**.

### Fixed

- The runner's outcome **warnings** box now shows its amber background and text
  colour in host apps. It previously relied on Tailwind utility classes
  (`bg-warning-50` …) that a host's Tailwind build never compiles for a package
  view; the colours are now literal CSS (light + dark).
- The tree-editor and runner breadcrumbs now Title-case the guides label, matching
  Filament's own resource breadcrumbs (was lower-case "guides").

## [0.4.0] - 2026-06-25

### Added

- **Tree editor rebuilt as a native Filament form.** Nodes and edges are now
  collapsible, reorderable repeaters with inline editing of existing items and
  per-type config fields (driven by each node type's `configSchema()`), so the
  authoring surface matches the rest of a Filament panel.
- **Live validation panel** in the editor — the engine's publish checks run
  against the current (unsaved) edits and list issues as you work, so publishing
  no longer surprises you.
- **Live preview** now builds from the current form state (updates as you edit,
  before saving); reactive fields refresh it without a full reload.
- **Test run** header action on the tree editor — saves the draft, then opens the
  runner for that version.
- **Per-version Metadata** section in the editor for the version's
  `extra_attributes` (seeds the guide on publish).
- **Back** button in the runner to step to the previous answer.
- **Breadcrumbs** on the tree editor and the version-keyed runner.
- Multi-language: per-locale translation inputs for outcome **warnings**
  (`warnings_i18n`), alongside prompt/verdict/text/option labels.
- Edges can no longer **loop a node to itself** (the trivial cycle); the `to`/`from`
  options exclude each other.

### Changed

- **Mermaid is now bundled into the asset** instead of fetched from a CDN at
  runtime — previews render fast and work offline. The diagram renderer is also
  debounced and skips unchanged sources, fixing slow/repeated re-renders.
- The resource's navigation item stays active on the tree-editor and runner pages.
- Upgrading now also wants `php artisan view:clear` (documented in the README and
  boost skill) so new markup takes effect.

## [0.3.0] - 2026-06-25

### Added

- **Permission gating UI** for the engine's `extra_attributes`: a "Required
  permissions" field on the guide form (authoritative copy), per-version
  "Edit metadata" action, copy-down of guide attributes onto each new draft, and
  a `decision-support-filament.permissions.options` config (free-form tags or a
  constrained multi-select). Enforcement stays in the host `Guide` policy.
- **Multi-language content**: `decision-support-filament.locales` /
  `fallback_locale` config; the tree editor renders a translation input per
  locale beside translatable fields (writing the node's `*_i18n` maps, blank
  inputs dropped), and the runner renders in the panel's active locale
  (`app()->getLocale()`) falling back to `fallback_locale` then the base string.
- The tree editor is **rebuilt with native Filament field components** —
  labelled, themed inputs with per-field **help text** sourced from each node
  type's `configSchema()` — replacing the previous hand-rolled inputs.
- Configurable navigation label for `GuideResource` via
  `decision-support-filament.navigation.label` (string or translation key; falls
  back to the default "Guides").
- Configurable singular/plural model labels via `decision-support-filament.labels`
  (`model`/`plural`; string or translation key).
- `decision-support-filament.forms.layout` (`'page'` default, `'modal'`,
  `'slideover'`) to create guides from the list without leaving it. Editing stays
  a full page so the versions relation manager still renders.
- Row **Run** action on the guide list table that opens the guide's currently
  active published version in the runner; disabled when no version is published.

### Changed

- The guide **form is wrapped in a native `Section`** so the create/edit pages
  look native out of the box.
- The guide `key` is now **locked after creation** (set once; it is the stable
  identifier a fact provider binds to) and `profile` is **locked once a version
  is published** (changing it could invalidate the live tree). Helper text
  updated accordingly.
- `GuideResource`, `ListGuides`, `CreateGuide`, and `EditGuide` are no longer
  `final`, so hosts can subclass them to restyle or relayout without forking.
- Requires `byjesper/laravel-decision-support` **^0.2** (for `extra_attributes`,
  multi-language content, and node `configSchema()` help text).
- Scoped `test:unit`/`test:parallel` to `tests/Unit` so the parallel runner never
  receives an empty (all-excluded) worker — the same paratest exit-code fix
  applied to the engine.

### Documentation

- README: added a "Getting the engine's Boost skill" note explaining that Laravel
  Boost only publishes skills from **direct** root `composer.json` dependencies.
  Because the engine is installed transitively via this package, its
  `decision-support-development` skill is not discovered unless the engine is
  required directly — documented with the `composer require` +
  `boost:update --discover` recipe.
- CONTRIBUTING: reflect that the package is now public (Packagist), with `main`
  protected and all changes flowing through pull requests.
- CHANGELOG: added the Keep a Changelog comparison link references so the version
  headings (including `[Unreleased]`) link to GitHub, matching the engine.

## [0.2.0] - 2026-06-24

### Added

- Extensible runner/editor pages: `GuideRunner` and `GuideTreeEditor` are no
  longer `final`, so hosts can subclass them.
- Pinned guide-keyed runner mode: a `GuideRunner` subclass that sets
  `$guideKey` drops the `{version}` route parameter, serves that guide's
  currently-active published version (404 when unknown/unpublished), and
  authorizes on the host `Guide` policy's `view` ability by default. Lets a host
  place a single guide in its own navigation with its own access gate.

## [0.1.0] - 2026-06-24

### Added

- `DecisionSupportPlugin` — a Filament plugin registered on a host panel with
  `->plugin(DecisionSupportPlugin::make())`.
- `GuideResource` (list/create/edit) with a versions relation manager that
  creates draft versions, links to the editor/runner, and publishes through the
  engine's validation pipeline.
- `GuideTreeEditor` page — node CRUD driven by each node type's `configSchema()`,
  a structured/expression/sentinel edge condition builder fed by the guide's fact
  vocabulary, a live Mermaid preview, and an inline-validating Publish action.
- `GuideRunner` page — drives the engine's resumable interpreter, renders
  question/lookup interactions, and shows the verdict over a reached-path Mermaid
  diagram.
- Bundled mermaid asset (`resources/dist/decision-support.js`, source in
  `resources/js/`) registered via `FilamentAsset`, so hosts do not manage the npm
  dependency.
- Permissive, host-overridable authorization that defers to a registered `Guide`
  policy.

[Unreleased]: https://github.com/byjesper/laravel-decision-support-filament/compare/v0.6.0...HEAD
[0.6.0]: https://github.com/byjesper/laravel-decision-support-filament/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/byjesper/laravel-decision-support-filament/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/byjesper/laravel-decision-support-filament/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/byjesper/laravel-decision-support-filament/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/byjesper/laravel-decision-support-filament/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/byjesper/laravel-decision-support-filament/releases/tag/v0.1.0
