# Changelog

All notable changes to `byjesper/laravel-decision-support-filament` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Documentation

- README: added a "Getting the engine's Boost skill" note explaining that Laravel
  Boost only publishes skills from **direct** root `composer.json` dependencies.
  Because the engine is installed transitively via this package, its
  `decision-support-development` skill is not discovered unless the engine is
  required directly — documented with the `composer require` +
  `boost:update --discover` recipe.

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
