# 💋 Kiss — Keep It Simply Static

A PHP static site generator. Uses **Twig** for templating, supports YAML/JSON/PHP/XML/INI data sources, dynamic page parameters, static file sync, and a *watch* mode with partial rebuilds.

## Prerequisites

- Docker & Docker Compose
- `inotify-tools` (for *watch* mode, host-side only)

## Getting started

```sh
make up                          # Start containers
make composer -- install         # Install dependencies
make kiss -- build               # Build the site
make kiss -- watch               # Build + watch for changes
```

## Commands

### Make

| Command | Description |
|---|---|
| `make up` | Start containers |
| `make down` | Stop containers |
| `make shell` | Bash into the `app` container |
| `make composer -- <args>` | Run Composer |
| `make tests` | Run all PHPUnit tests |
| `make test -- <args>` | Run a specific test (`make test -- tests/KissTest.php`) |
| `make phpcs` | PSR-12 lint |
| `make phpstan` | Static analysis level 5 |
| `make coverage` | HTML coverage report (`coverage/index.html`, requires `XDEBUG_MODE=coverage`) |
| `make kiss -- <args>` | Run the CLI (`src/bin/kiss`) |
| `make tag` | Git tag from `composer.json` version (requires `jq`) |

> ⚠️ Make swallows flags (`-y`, `--version`). Workaround: `make kiss -- build` or `make kiss -- --version`.

### CLI (via `make kiss --`)

| Command | Description |
|---|---|
| `kiss build` | Build the whole site |
| `kiss watch` | Build then watch files with `inotifywait` |
| `kiss reset [all\|dist\|cache]` | Remove `web/` and/or `tmp/` directories |
| `kiss route [list\|name]` | List routes or rebuild a specific route |
| `kiss copy [path]` | Sync a file from `copy/` to `web/` |
| `kiss test` | Validate configuration (warmup without build) |

## Project structure

```
kiss.yml        → Site configuration
├── copy/       → Static files (copied as-is to web/)
├── data/       → Global data (YAML/JSON/PHP/XML/INI/MD)
├── route/      → Route definitions (YAML/JSON/PHP/XML/INI)
├── template/   → Twig templates
├── web/        → Generated site (dist)
└── tmp/        → Cache (resolved to /tmp/kiss/<hash>)
```

## Configuration

File `kiss.yml` (also supports `.yaml`, `.php`, `.json`, `.xml`, `.ini`).

```yaml
debug: true
path:
  copy: copy
  data: data
  route: route
  template: template
  dist: web
  cache: tmp
```

Environment variables (take precedence):
- `KISS_DEBUG=true`
- `KISS_VERBOSE=true`

## Data sources

Files in `data/` are loaded automatically and available in Twig via `{{ global.* }}`.

Example — `data/site.yml`:
```yaml
name: My Site
tagline: Great static site
```

In a Twig template:
```twig
<h1>{{ global.site.name }}</h1>
<p>{{ global.site.tagline }}</p>
```

### Markdown files

Markdown files (`.md`) in `data/` support frontmatter delimited by `---`:

```markdown
---
title: My Article
date: 2024-01-01
---
Content **markdown** here.
```

The frontmatter fields are available directly, and the body is available as `content`. Combine with the `|markdown` filter in your templates:

```twig
<h1>{{ title }}</h1>
<div>{{ content|markdown|raw }}</div>
```

## Routes

### Single page

`route/index.yml`:
```yaml
path: /index.html
template: page.twig
data:
  title: Home
```

### Multiple pages with parameters

`route/blog.yml`:
```yaml
path: /{slug}.html
template: post.twig
data:
  - slug: hello-world
    title: Hello World
  - slug: second-article
    title: Second article
```

### External reference ($ref)

```yaml
path: /{slug}.html
template: post.twig
data:
  $ref: data/articles.yml
```

## How it works

- **Route manifest**: `tmp/kiss/route-manifest.php` tracks which files each route generated; orphaned files are cleaned on rebuild.
- **Template deps**: `tmp/kiss/template-deps.php` traces dependencies between templates (extends/include/embed) for partial rebuilds.
- **DataTree**: caches remote and local data sources as serialized PHP files.
- **Watch**: `inotifywait` detects changes and recompiles only the impacted routes.

## Tests

```sh
make tests                       # All tests
make test -- --filter=testBuild  # Specific test
make coverage                    # HTML report
```

Tests create temporary directories in `sys_get_temp_dir()/kiss-test-*` and clean them up automatically. All tests are unit tests (no integration tests).

## License

MIT
