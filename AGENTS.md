# AGENTS.md — Kiss PHP (Static Site Generator)

## Dev environment (Docker)
- `make up` starts containers. `make down` stops them.
- All tool commands run through `docker compose exec app`.
- `make shell` / `make shell-root` to get a shell.
- `.env` controls `XDEBUG_MODE` (set to `coverage` by default).

## Commands
- `make tests` → run all PHPUnit tests.
- `make test -- --filter=<name>` → run a single test (note `--` to bypass Make arg capture).
- `make kiss -- <args>` → run CLI tool inside container.
- `make composer -- <args>`, `make phpcs -- <args>`.
- `test-watch` → inotify-based auto-test loop (requires `inotify-tools` on host).
- `make logs` → container logs.

## Architecture
- Namespace `Kiss\` → `src/` (PSR-4). CLI entry: `src/bin/kiss`.
- All components in `src/` (`Kiss`, `DataSource`, `DataTree`, `Route`, `RouteCollection`, `CopySync`, `Log`, `Tools`).
- Config file: `kiss.yml` (auto-created on first run). Env overrides: `KISS_DEBUG`, `KISS_VERBOSE`.

## Testing
- PHPUnit 11, uses vfsStream for virtual filesystem tests.
- Run focused: `make test -- --filter=testLoadFromPhp`.
- PHPCS (PSR12) via `make phpcs`.

## Code style
- PSR12 via PHPCS.
- VSCode: intelephense for completion.

## Remaining (from TODO.md)
- Image thumbnails from Twig function (not started)
