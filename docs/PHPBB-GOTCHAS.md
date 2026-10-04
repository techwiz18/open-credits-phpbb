# phpBB dev gotchas (learned porting OpenCredits)

## 1. Amounts: `DECIMAL` is signed, `UINT` is not

phpBB DBAL `UINT/USINT/ULINT/BOOL/TIMESTAMP` map to `UNSIGNED` on MySQL.
`DECIMAL:10` (= `decimal(10,2)`) is **signed** — use it for `amount`/`balance`
columns so debit rows work. (XF trap equivalent: numerics default unsigned there too.)

## 2. Template-event filenames must match exactly

`styles/all/template/event/<event_name>.html` — one char off and the hook silently
never fires. Postbit: `viewtopic_body_postrow_custom_fields_after`;
profile: `memberlist_view_user_statistics_after`.

## 3. No core like/thanks event — verified

phpBB 3.3 ships **no** thanks/like button and fires **no** core event for one.
`reaction_received`-equivalent requires an optional integration with
`gfksx/Thanks for posts` or `avathar/postlove` (Phase 2). MVP ships
post/topic/register/daily-visit only.

## 4. Daily grant: session guard, not cron

phpBB cron tasks (`phpbb\cron\task\base`, traffic-fired via `cron.php`) miss timing
and give no per-user guarantee. Use `core.user_setup` + exactly-once guard
(ledger-today check) like XF's `visitor_setup` pattern.

## 5. Times are ints, no FKs

Idiomatic time column is `TIMESTAMP` (unix int), not `DATETIME`. No FK type in
migrations — use `KEYS => INDEX` + application logic.

## 6. Migrations are append-only

Never edit an installed migration. New schema/data = new migration file with
`depends_on()` on the previous one. `effectively_installed()` guards re-runs.

## 7. Permissions need the trio

`permission.add` in migration + `language/en/permissions_oc.php`
(`$lang['ACL_U_OC_VIEW']`) + `core.permissions` listener
(`$event->update_subarray(...)`, `cat=misc`). Missing any one = invisible permission.

## 8. `www/` is disposable

The phpBB runtime is gitignored. `config.php`, `store/`, `cache/`, `files/` all live
under `www/` — safe to `rm -rf www` + `dev.sh unpack` to start over.
