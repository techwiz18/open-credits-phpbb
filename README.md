# OpenCredits for phpBB — open-source credits for phpBB 3.3

[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net/) [![phpBB 3.3](https://img.shields.io/badge/phpBB-3.3-orange)](https://www.phpbb.com/) [![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)

A free (MIT) credits system for phpBB 3.3: reward activity, show balances,
let members tip each other. A phpBB twin of
[OpenCredits for XenForo](https://github.com/techwiz18/open-credits).

## What members get

* **Earn credits** for new threads, replies, registering, and visiting daily —
  amounts managed in ACP → Extensions → OpenCredits → Earn triggers.
* **Multiple currencies** (e.g. Credits, loyalty points), each with its own
  balance, managed in ACP → Extensions → OpenCredits → Currencies.
* **Wallet everywhere** — the main balance in the navbar and postbit, every
  currency in the UCP front page and on member profiles, full per-currency
  history in the UCP OpenCredits tab.
* **Transfers and tips** — send credits from the wallet tab, or hit Donate on
  any post or profile (recipient and currency pre-filled; transfers can never
  overdraw).

## Requirements

* phpBB 3.3.x, PHP 8.1–8.3, MySQL 5.7+/8.0 (or MariaDB equivalent).
* No other extensions required.

## Install on your forum

1. Download the latest `OpenCredits-PhpBB-x.y.z.zip` from the
   [releases page](../../releases) and unzip it on your computer.
   Inside you'll find an `ext/` folder.
2. Upload its **contents** (`techwiz18/opencredits/`) into your board's `ext/`
   folder (the one containing `phpbb/`), so `composer.json` lands at
   `ext/techwiz18/opencredits/composer.json`. Merging folders is fine.
3. Purge the cache (ACP → General → Purge the cache).
4. ACP → Customise → Manage extensions → enable **OpenCredits**.

No template or theme edits are needed — balances, donate links, and the
navbar entry inject automatically on prosilver-based styles.

Then:

1. Permissions (`u_oc_view`, `u_oc_transfer` under Misc) are auto-allowed for
   Registered on install — adjust per group in ACP → Permissions if needed.
2. Members start earning on the next post/visit. Balances appear in the
   postbit and navbar automatically.

## Admin map

* **Extensions tab → OpenCredits → Currencies** — titles, prefixes/suffixes,
  active/visible flags, exactly one primary (the postbit currency), add new
  currencies.
* **Extensions tab → OpenCredits → Earn triggers** — per-trigger amount,
  currency, max awards per day, forum allowlist, on/off, plus add-trigger
  rows so new currencies can earn.
* **Rebuild** — Extensions tab → OpenCredits → **Tools** recomputes every
  balance from the append-only transaction log (use if balances ever look
  wrong), or CLI (`php bin/phpbbcli.php opencredits:rebuild`). Disabling the
  extension keeps all data; deleting its data wipes it.

## Earning defaults

| Action | Default |
|---|---|
| New thread | $5.00 |
| Reply | $1.00 |
| Registration | $10.00 |
| Daily visit (once/day) | $5.00 |

Change amounts, add triggers, or add currencies any time in the Extensions
tab → OpenCredits.

## Troubleshooting

* **Balances look wrong** — run the rebuild above; every balance is re-derived
  from the ledger.
* **Wallet/transfer pages say no permission** — check the Misc permissions for
  the member's groups (`u_oc_view`, `u_oc_transfer`).
* **A new currency shows nowhere** — currencies only appear once Active; they
  only earn once a trigger row points at them.

## For developers

* [docs/DEV.md](docs/DEV.md) — Docker dev environment in minutes.
* [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — code map, schema, trigger
  matrix (written as an LLM/contributor reference).
* [docs/PHPBB-GOTCHAS.md](docs/PHPBB-GOTCHAS.md) — hard-won phpBB 3.3
  conventions (reserved words, module depths, template events…).
* [docs/THANKS-INTEGRATION.md](docs/THANKS-INTEGRATION.md) — deferred design
  for a thanks-received trigger (ThanksForPosts is not bundled with phpBB).

## Built with AI

This project is vibe-coded: the code, docs, and much of the testing plan were
produced with AI assistance and reviewed by a human. If you deploy it, treat it
like any community extension — review security-sensitive changes and keep backups.

## License

MIT — see [LICENSE](LICENSE).
