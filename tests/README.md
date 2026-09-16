# Testing

For builder request ordering and failure recovery tests, install the JavaScript
dependencies with `npm ci`, then run `npm test` from this checkout. These tests
exercise the actual store with a controlled Craft transport. `npm run build`
checks TypeScript and builds the production assets.

Install [DDEV](https://docs.ddev.com/en/stable/users/install/ddev-installation/)
and a supported Docker provider (OrbStack works on macOS). From this plugin's
checkout, run:

```sh
ddev test
ddev test --filter='a test name'
ddev test --suite=all
ddev test --suite=performance
```

The performance suite measures resolver, builder and reorder processing for synthetic
50-, 250-, and 1,000-item provider catalogs, with empty overrides, a single override, populated overrides
and nested items. Warm operations have a fixed query budget to catch per-item queries. It writes median timings to
`.cache/cpnav-performance.json`, excluding the first warm-up iteration. Compare
runs on the same machine; these are PHP processing measurements, not browser
load times. The test checks distinct output keys and stored parentage without imposing hardware-dependent
timing thresholds. Provider-call assertions separately verify request and shared cache reuse.

The command starts the dedicated test project, installs dependencies inside DDEV,
creates a clean Craft application, installs this checkout as a Composer path
dependency, seeds plugin fixtures and runs Pest. No separate Craft site, host PHP,
host Composer, database setup or `.env.testing` file is required. The root Composer
`test` aliases call this same command if you already have Composer on your host.

Tests run against real Craft. The PHPUnit XML discovers PHP tests; the suite
manifest in `tests/runtime/suite.json` defines intentional group exclusions.
The default excludes slow, performance, large-performance and migration-plugin
groups. Some plugins have additional suites listed in that manifest. Test files
named `Unit` may still rely on the Craft application.

Each invocation rebuilds database, project configuration and storage under
`.cache/verbb-tests/app`. Dependencies are cached between runs. The generated app
loads the plugin from this checkout; developer `.env` files and paired sites are
not used. Tests must not be pointed at an external database. Run serially; parallel
workers are rejected until they have independent state.

The DDEV project name is stable. Re-running tests does not allocate another
project. Use `ddev stop` when finished; use `ddev delete` from this checkout to
remove this dedicated project's containers and database volume. The next test run
recreates its baseline. Keep reports before deleting generated files.

Results and combined setup/test output are written to `.cache/verbb-tests/result.json`
and `.cache/verbb-tests/latest.log`; Craft logs remain in the generated app's
storage. A failed setup exits nonzero and does not run tests against partial state.

To exercise the real builder in Chromium, run these after a successful `ddev test`:

```sh
npx playwright install chromium
npm run test:browser
```

The browser suite uses the same disposable app and its normal Craft login and CSRF
handling. It seeds three named layouts and a user group, then checks creation, edits, saved order,
failed reorder recovery, visibility, the SVG picker, deletion, layout switching and
reset through the UI. It also checks the active parent and child on a real CP page. Layout management
covers saved group assignments, literal names, duplication after a rename and deletion.
Actual dragging covers nesting and moving a collapsed subtree, with exact order and
depth verified after reload, and preservation of root-level placement when dropping onto a divider.
It replaces only those named browser fixtures on subsequent runs. Do not run PHP
and browser tests concurrently: PHP runs rebuild this application. Failure traces
and screenshots are written to `test-results/`; the HTML report is in
`playwright-report/`. CI runs PHP, frontend tests, the production build and Chromium.

`AdversarialPayloadTest.php` checks nested or invalid values, duplicate and unknown
reorder keys, manual URL and boolean validation, and layout form boundaries.
The browser audit checks actual HTTP authentication, CSRF and method rejection,
literal rendering of hostile labels, SVG script isolation and traversal rejection.
Its synthetic large-tree run records browser readiness and editor paint timings as
a test attachment. These diagnostics include the local browser and host workload;
they are not fixed CI performance thresholds. Keyboard opening of the row menu is
checked alongside the pointer workflow.

The shared PHP fixture restores project config, users, request state, settings and
caches. Use `fixtureLayout`, `fixtureEditor`, `fixtureProvider` and `fixtureIcons`
for scenarios with explicit inputs. Call `nextConfigRequest()` when simulating a
subsequent request: Craft deduplicates project-config callbacks within a request.
`NavContentCatalogTest.php` adds and removes a real Craft section after warming
both catalog caches, checking native ordering and preservation of a saved override.
Required installation data should fail an assertion if missing; do not silently
skip a test that can seed or enable the feature it exercises.

The runtime scaffold is committed with the plugin, so no private Verbb tooling or
sibling checkout is needed. Plugin-specific test fixtures belong in this repository.
Do not add database/schema repair to the PHPUnit bootstrap: fresh installation must
work through the normal Craft/plugin installation path first.

The initial runtime is PHP 8.3 and MySQL 8.0. This environment is not a claim of
complete coverage for every supported Craft/PHP/database version. Compatibility
matrix expansion must validate the actual runtime and fixture behavior.

The test application's dependency baseline is versioned in `tests/runtime/composer.lock`.
Use `ddev test --update-lock` when intentionally updating that baseline, and review
the lock diff alongside the test results. This does not update the plugin's root lock.
JUnit results are available in `.cache/verbb-tests/junit.xml`. Tests exceeding 60 seconds
are reported as failures; annotate genuinely long-running tests with PHPUnit size metadata.

Existing performance-report and baseline-maintenance aliases also provision through
this runner, using the named `--task=` entries in `suite.json`. These explicitly
requested maintenance tasks report `completed-task`, not a passing Pest suite.

`MigrationRecoveryTest.php` covers interrupted import boundaries, existing beta
customizations, archived reimports, duplicate identities, missing parents and manual
replacement of legacy asset icons. Run these cases with:

```sh
ddev test tests/Services/MigrationRecoveryTest.php
```

These database-backed cases complement `MigrationWorkflowTest.php`, which checks
conversion and project-config rebuild/apply. They use the disposable test database;
the interrupted states represent persisted migration boundaries rather than a
process being killed during a database statement.
