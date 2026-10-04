# Development setup (Docker)

Contributor dev stack on Linux. Mirrors the XenForo `open-credits` `docs/DEV.md` approach, but for phpBB.

## Requirements

Docker 29+ and Compose 2.40+ (`docker compose version`). No local PHP/MySQL needed.
A phpBB 3.3.x full package zip is required for the runtime but is **never committed**
(`www/` is gitignored; you must supply your own copy for dev only).

## First run

```bash
cp /path/to/phpBB-3.3.19.zip ../phpBB-3.3.19.zip   # parent dir, any 3.3 full package
./scripts/dev.sh up        # build + start web/db/phpmyadmin
./scripts/dev.sh unpack    # unzips ../phpBB-3.3*.zip into www/
# open http://localhost:8090/install — DB host: db, name: phpbb_dev, user: phpbb, pass: phpbbdev
```

The extension source (`ext/techwiz18/opencredits/`) is bind-mounted live into the
container at `/var/www/html/ext/techwiz18/opencredits/`; edits apply on next page load
(purge the cache in ACP if templates don't refresh).

## Useful commands

| Command | Purpose |
|---|---|
| `./scripts/dev.sh up/down/logs` | lifecycle |
| `./scripts/dev.sh link-ext` | verify the bind-mount inside the container |
| `docker compose exec web ls -la /var/www/html/ext/techwiz18/opencredits/` | same, manual |

* Forum: http://localhost:8090 · phpMyAdmin: http://localhost:8091 (root/rootpass)
* `www/config.php` holds dev DB creds after install (gitignored via `www/`).

## phpBB version notes

* Target: phpBB 3.3.19 (repackage of 3.3.18 security fix, 2026-09-24).
* PHP 8.1–8.3 supported; Docker uses `php:8.2-apache`. phpBB 3.3 does NOT support PHP 8.4.
* DB: `mysql:8.0` with `utf8mb4_unicode_ci`.
