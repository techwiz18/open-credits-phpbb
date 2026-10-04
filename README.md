# OpenCredits for phpBB

Multi-currency credits wallet for phpBB 3.3 — a phpBB twin of the XenForo
[OpenCredits](https://github.com/techwiz18/open-credits) addon. MIT licensed.
No real-money features.

## Features (v0.3.x)

* **Multi-currency**: primary + visible flags; append-only transaction ledger
  with cached per-currency balances (missing row means zero).
* **Earn triggers**: new thread, reply, registration, daily visit — amounts,
  per-day limits, and forum allowlists configurable per currency, with
  overlapping rows that stack.
* **Member transfers**: double-entry, floored at zero, never overdraws.
  Donate links on posts and profiles open the transfer form pre-filled.
* **Wallet UI**: UCP tab (paged history + transfer form), navbar balance,
  postbit / profile / front-page balances — all gated on view permission.
* **Admin**: ACP currency + trigger management, `opencredits:rebuild` CLI to
  rebuild balances from the ledger.

## Requirements

* phpBB 3.3.x, PHP 8.1–8.3, MySQL 5.7+/8.0 (or MariaDB equivalent).

## Install (forum owners)

This repo is the development workspace; the shippable extension is the
`ext/techwiz18/opencredits/` directory inside it.

1. Copy `ext/techwiz18/opencredits/` to your board at
   `ext/techwiz18/opencredits/` (so `composer.json` lands at
   `ext/techwiz18/opencredits/composer.json`).
2. Purge the cache (ACP → General → Purge the cache).
3. ACP → Customise → Manage extensions → enable **OpenCredits**.
4. Permissions are granted to Registered by default (`u_oc_view`,
   `u_oc_transfer` under Misc); tune in ACP → Permissions.
5. Configure amounts in the Extensions tab → OpenCredits → Earn triggers.

## Development (Docker)

No local PHP/MySQL needed — see `docs/DEV.md` for the full walkthrough.

```bash
cp /path/to/phpBB-3.3.19.zip ../phpBB-3.3.19.zip  # full package, parent dir
./scripts/dev.sh up        # build + start web/db/phpmyadmin
./scripts/dev.sh unpack    # unzips into www/ (gitignored, never committed)
# open http://localhost:8090/install — DB host: db, name: phpbb_dev, user: phpbb, pass: phpbbdev
```

* Forum: http://localhost:8090 · phpMyAdmin: http://localhost:8091
* The extension bind-mounts live into the container; enable it in ACP → Customise.

## Docs

* `docs/ARCHITECTURE.md` — code map, tables, trigger matrix.
* `docs/DEV.md` — dev-stack setup and commands.
* `docs/PHPBB-GOTCHAS.md` — porting lessons (reserved words, module depths, template events…).
* `docs/THANKS-INTEGRATION.md` — deferred design for a thanks-received trigger.

## License

MIT — see `LICENSE`.
