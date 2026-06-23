# Package Structure

- A package is a library, not an application: no `App\` namespace, no app
  bootstrap. Tests boot a minimal app via Testbench.
- Register bindings in the service provider's `register()`; wire publishing,
  commands, migrations, and routes in `boot()`, guarding console-only work with
  `runningInConsole()`.
- Ship runtime files only. The `.gitattributes` `export-ignore` rules keep
  tests, CI, and tooling out of the dist tarball.
- Do not commit `composer.lock` — it is git-ignored for libraries.
- Guidelines meant for *consumers* of the package belong in `resources/boost/`
  (Boost auto-discovers them), not `.ai/guidelines/`, which is local to
  developing the package itself.
