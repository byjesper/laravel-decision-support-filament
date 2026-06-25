# Changelog

All notable changes to `byjesper/laravel-decision-support-filament` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/byjesper/laravel-decision-support-filament/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/byjesper/laravel-decision-support-filament/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/byjesper/laravel-decision-support-filament/releases/tag/v0.1.0
