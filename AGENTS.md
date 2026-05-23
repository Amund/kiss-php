# kiss-php — Agent Guide

A PHP static site generator ("Keep It Simply Static"). Namespace `Kiss\`, entry config `kiss.yml`.

## Dev environment

Requires PHP 8.2+ and `inotifywait` (Linux/WSL) or `fswatch` (macOS).

```sh
php src/bin/kiss build    # build
php src/bin/kiss watch    # watch for changes
```

## Commands

| `make <target>` | What it does |
|---|---|
| `tests` | Run all PHPUnit tests |
| `test -- <phpunit-args>` | Run single test file: `make test -- tests/KissTest.php` or `make test -- --filter=testBuild` |
| `phpcs` | Lint (PSR-12) via `vendor/bin/phpcs src tests` |
| `phpstan` | Static analysis at level 5 (`php -d memory_limit=512M vendor/bin/phpstan analyse`) |
| `coverage` | Generate HTML coverage report in `coverage/` (requires `XDEBUG_MODE=coverage` in `.env`) |
| `kiss -- <cmd>` | Run the CLI (`src/bin/kiss`) inside the container |

**CI order**: lint → typecheck → test (each is independent).

## Project layout

```
src/            → class source (PSR-4: Kiss\)
src/bin/kiss    → CLI entrypoint
tests/          → PHPUnit tests (one file per class)
tests/fixture/  → test data fixtures
```

Config file is `kiss.yml` (also supports `.yaml`, `.php`, `.json`, `.xml`, `.ini`).
Env overrides: `KISS_DEBUG=true`, `KISS_VERBOSE=true` take precedence over config file.

## Architecture notes

- **Entry**: `kiss.yml` (or any supported ext in `Config::EXTENSIONS`)
- **Paths** (default): `copy/` → static files, `data/` → global data, `route/` → route definitions, `template/` → Twig templates, `web/` → output dist, `tmp/` → cache (resolved to `/tmp/kiss/<md5>/kiss`)
- **Route types**: single page (`data` = contexte), param pages (`items` = liste, `data` = méta), pagination (`paginate` + `items`)
- **Data sources**: yaml/json/php/xml/ini/md files in `data/`, available as `{{ global.* }}` in Twig. Markdown files are parsed for frontmatter (`---` delimited YAML) and body (`content` key). Routes can use `$ref` to reference external data files.
- **Watch mode**: `php src/bin/kiss watch`, uses `inotifywait` (Linux/WSL) or `fswatch` (macOS)
- **Route manifest**: cached in `tmp/kiss/route-manifest.php` to track which files each route generated; stale files are cleaned on rebuild
- **Template deps**: `TemplateDeps` tracks which templates each route uses for partial rebuilds
- **URL generation**: `path(routeName, params)` Twig function via `UrlGenerator`
- **Cross-route data**: `route(routeName)` Twig function returns resolved `data + items`
- **Pagination**: `paginate` property on route, outputs `{path}/index.html` + `{path}/page/{n}/index.html`
- **items**: mandatory when path has `{params}` or `paginate`; `data` is metadata only

## Testing quirks

- Tests create temp dirs in `sys_get_temp_dir()/kiss-test-*` and clean up via `RmDirTrait`
- Tests expect `template/` dir to exist (call `$kiss->warmup()`)
- Test output is captured via `expectOutputRegex()` in some cases
- No integration/contract tests — all unit tests

## Style

- **PSR-12** enforced by `phpcs` (`vendor/bin/phpcs src tests`)
- Final classes, typed properties, strict comparisons
- No trailing whitespace, no tabs (spaces for indent)
