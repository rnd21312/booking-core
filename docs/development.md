# Development

## Prerequisites

- PHP 8.1+, Node.js 20+
- A WordPress to run it in. Docker is optional — [WordPress Playground](https://wordpress.github.io/wordpress-playground/) is enough.

## Front end

```bash
cd frontend
npm ci
npm run dev          # Vite dev server for the admin apps (http://localhost:5174)
npm run build        # production bundle → ../dist
npm run typecheck
npm run lint
npm test             # Vitest
```

## PHP tests

The unit tests only need PHPUnit and the bootstrap in `tests/bootstrap.php`:

```bash
php phpunit.phar -c phpunit.xml.dist       # or: vendor/bin/phpunit if you installed it with Composer
```

Download PHPUnit 10/11 from https://phpunit.de if you do not have it.

## Run WordPress with Playground

```bash
npx @wp-playground/cli@latest server \
  --mount-dir ./suntourz-core  /wordpress/wp-content/plugins/suntourz-core \
  --mount-dir ./suntourz-theme /wordpress/wp-content/themes/suntourz-theme
```

(Build both front ends first. On Windows use PowerShell/cmd and `--mount-dir`.) Playground uses SQLite, so for real concurrency / `SELECT … FOR UPDATE` tests of the seat lock use a MySQL-backed site.

## Conventions

- `declare(strict_types=1)` everywhere, typed properties, no globals except WordPress APIs.
- New columns or tables: bump the schema version and extend `Schema::install()`.
- New REST route: controller in `src/Rest`, register it in `Plugin.php`, document it in `docs/rest-api.md`.
- Keep pricing and seat rules in `Domain/` and unit-test them.

## Release

1. Bump the version in `suntourz-core.php` (header + `STZ_CORE_VERSION`) and `CHANGELOG.md`.
2. `git tag v0.1.1 && git push --tags`.
3. The **Release zip** workflow builds the admin bundle and attaches `suntourz-core.zip` to the release.

## Contributing

Issues and PRs are welcome. Run the front-end checks and the PHP tests first, and keep PRs focused. Contributions are licensed GPL-2.0-or-later.
