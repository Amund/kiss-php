# 💋 Kiss - Keep It Simply Static

A PHP static site generator. Uses **Twig** for templating, supports YAML/JSON/PHP/XML/INI data sources, dynamic page parameters, static file sync, and a *watch* mode with partial rebuilds.

## Prerequisites

- PHP 8.2+ (CLI)
- `inotifywait` (Linux/WSL) ou `fswatch` (macOS) pour le mode *watch*

## Getting started

```sh
composer global require amund/kiss-php
kiss init my-site
cd my-site
kiss build
```

## CLI

| Command | Description |
|---|---|
| `kiss init [dir]` | Creates a new site in the folder (or the current folder) |
| `kiss build` | Build the entire website |
| `kiss watch` | Build and then monitor the files (using inotifywait or fswatch) |
| `kiss reset [all\|dist\|cache]` | Remove `web/` and/or `tmp/` |
| `kiss route [list\|name]` | List the routes or rebuild a specific route |
| `kiss copy [path]` | Sync a file from `copy/` to `web/` |
| `kiss test` | Validate the configuration (warmup without build) |

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

### Pages with settings (`items`)

`route/blog.yml`:
```yaml
path: /{slug}.html
template: post.twig
data:
  blog_title: "My Blog"
items:
  - slug: hello-world
    title: Hello World
  - slug: second-article
    title: Second article
```

Each item generates a page. `data` contains the metadata, which is merged into each child page.

### External reference (`$ref`)

```yaml
path: /{slug}.html
template: post.twig
items:
  $ref: data/articles.yml
```

### Archive page + list

```yaml
path: /blog
template: blog_list.twig
data:
  title: "Blog"
items:
  $ref: data/posts.yml
```

Access in the archive template :
```twig
<h1>{{ title }}</h1>
{% for post in items %}
  <a href="{{ path('blog', {slug: post.slug}) }}">{{ post.title }}</a>
{% endfor %}
```

### URL Generation (`path()`)

```twig
{{ path('about') }}                   → /about
{{ path('blog', {slug: 'hello'}) }}   → /hello.html
{{ path('blog') }}                     → /blog (page 1)
{{ path('blog', {page: 2}) }}          → /blog/page/2
```

### Accessing data from another route (`route()`)

```twig
{% set blog = route('blog') %}
<h1>{{ blog.title }}</h1>
{% for post in blog.items %}
  ...
{% endfor %}
```

### Pagination

`route/blog.yml`:
```yaml
path: /blog
template: blog_list.twig
paginate: 10
data:
  title: "Archives"
items:
  $ref: data/posts.yml
```

Generates `blog/index.html` (page 1) and `blog/page/{n}/index.html` (subsequent pages).

```twig
{% for post in items %}
  ...
{% endfor %}
{% if prevPage %}
  <a href="{{ path('blog', {page: prevPage}) }}">← Previous</a>
{% endif %}
{% if nextPage %}
  <a href="{{ path('blog', {page: nextPage}) }}">Next →</a>
{% endif %}
```

### Validation

- `{param}` or `paginate` → `items` is required
- `data` is reserved for metadata
- The `$ref` should be set to `items`, not `data`

## How it works

- **Route manifest**: `tmp/kiss/route-manifest.php` tracks which files each route generated; orphaned files are cleaned on rebuild.
- **Template deps**: `tmp/kiss/template-deps.php` traces dependencies between templates (extends/include/embed) for partial rebuilds.
- **DataTree**: caches remote and local data sources as serialized PHP files.
- **Watch**: `inotifywait` detects changes and recompiles only the impacted routes.

## License

MIT
