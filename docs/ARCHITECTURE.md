# OpenCredits for phpBB — architecture (contributor reference)

Port of the XenForo OpenCredits concepts (see `open-credits/docs/ARCHITECTURE.md`):
multi-currency (primary + visible flags), append-only transaction ledger plus cached
per-currency balance table, earn triggers, double-entry transfers that never overdraw,
permission-gated wallet UI. Re-implemented against phpBB idioms — no XenForo code copied.

Source of truth: `ext/techwiz18/opencredits/`. It is bind-mounted into the dev forum
container at `/var/www/html/ext/techwiz18/opencredits/` (see `docker-compose.yml`).
The phpBB runtime in `www/` is gitignored and never committed.

## File map (Phase 1 skeleton → full)

| Path | Role |
|---|---|
| `composer.json` | Extension meta. `techwiz18/opencredits`, MIT, requires phpBB ~3.3 |
| `ext.php` | Enable/disable checks (`is_enableable`: phpBB + PHP version) |
| `config/services.yml` | Listener + Transact service definitions |
| `event/main_listener.php` | All core-event subscriptions (no business logic) |
| `service/transact.php` | **Only place that writes balances.** Row lock + txn + ledger. Integer-cents math; `adjust()` unrestricted, `transfer()` never overdraws |
| `migrations/v10x/*.php` | Schema + seed data + ACP/UCP module + permission installs |
| `acp/main_info.php`, `acp/main_module.php` | ACP currency/event management |
| `ucp/main_info.php`, `ucp/main_module.php` | Wallet history + transfer form |
| `controller/` (+ `config/routing.yml`) | Only if a public/AJAX page is needed beyond UCP |
| `language/en/*.php` | `common`, `permissions_oc`, `info_acp_oc` |
| `styles/all/template/` | UCP templates + `event/` template-event hooks |
| `cron/task/*.php` | Only for sweeps/expiry — NOT for per-user daily grants |

## Database (planned)

* `phpbb_oc_currency(currency_id, title, prefix, suffix, decimals, allow_negative, active, is_primary, visible)` — seed `(1, 'Credits', '$', '', 2, 0, 1, 1, 1)`. Exactly one primary; `visible` lists in postbit/menu.
* `phpbb_oc_balance(user_id, currency_id, balance)` — cached per-currency balances maintained by Transact; missing row means 0.
* `phpbb_oc_event(event_id, currency_id, trigger_name, amount, forum_ids, max_per_day, active)` — seed: `thread 5.00`, `post 1.00`, `register 10.00`, `daily_login 5.00`. (`reaction_received` deferred — no core like event, see GOTCHAS.)
* `phpbb_oc_transaction(id, user_id, currency_id, amount, trigger_name, content_id, note, log_time)` — append-only ledger. **Amounts SIGNED** (`DECIMAL:10`); `UINT` family is unsigned in phpBB DBAL.
* No `users` table alteration in v1 — primary balance reads from `phpbb_oc_balance`.

## Trigger matrix (live since Phase 2; listener → service call)

| Core event | Listener method | Trigger awarded to |
|---|---|---|
| `core.submit_post_end` (new topic, first post) | `on_submit_post` | author, `thread`, visible/approved only |
| `core.submit_post_end` (reply/quote) | `on_submit_post` | author, `post`, skips edits |
| `core.user_add_after` | `on_user_add` | new user, `register` |
| `core.user_setup` (session guard) | `on_user_setup` | visitor, `daily_login` once/day, skip reg day |

All earning paths funnel into `Transact::awardByTrigger()` → `adjust()`.
Transfers are double-entry (debit sender + credit recipient) floored at zero, never overdraw.
Rebuild = ledger → `phpbb_oc_balance` (CLI/cron, Phase 2+).

## Frontend (live since Phase 3)

* UCP module `history` (ledger, paged 20) + `transfer` (form + save, `form_token` CSRF, `transfer()` refusal surfaced as insufficient-funds).
* Template events: `viewtopic_body_postrow_custom_fields_after` (postbit, visible currencies),
  `memberlist_view_user_statistics_after` (profile, per-currency rows),
  `ucp_main_front_user_activity_append` (UCP front summary, all active incl. zero).
  Data via `core.viewtopic_modify_post_row` /
  `core.memberlist_modify_view_profile_template_vars` /
  `core.ucp_display_module_before` (front-page gate).
* Permissions `u_oc_view` (history + all balance display), `u_oc_transfer` (transfer). Default deny; data migration auto-allows for Registered.

## Conventions for new work

* Never write balances outside `service/transact.php`.
* New triggers: seed row in data migration + listener method + migration version bump.
* Migrations are immutable once installed — new changes get new migration files.
* No real-money features in v1. MIT license.
