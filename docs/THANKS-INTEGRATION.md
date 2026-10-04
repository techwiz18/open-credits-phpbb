# Thanks integration design (Phase 4 candidate — no code yet)

Goal: a `thanks_received` earn trigger mirroring the XenForo twin's
`reaction_received 2.00`, without forking the upstream extension.

## Target

`gfksx/ThanksForPosts` (`rxu/thanks_for_posts`, validated for phpBB 3.3.x,
~42k downloads). No other thanks/like extension is assumed; `avathar/postlove`
would need a parallel adapter (different table/actions).

## What we learned (read-only review of their `master`)

* Ledger table: `{prefix}thanks(post_id, poster_id, user_id, topic_id, forum_id,
  thanks_time)` — `poster_id` is the post author, `user_id` the thanker.
* They dispatch **no custom events**. A thanks arrives as `GET ?thanks=<post_id>`
  on viewtopic and is inserted inside their `core.viewtopic_get_post_data`
  handler (`viewtopic_handle_thanks`, priority **-2**) via
  `helper->insert_thanks($post_id, $user_id, $forum_id)`.
  Removal is `GET ?rthanks=<post_id>` → `delete_thanks()`.
* Their own dedupe: one thanks per (user, post). Self-thanks are refused in
  `insert_thanks`.

## Proposed design (soft dependency, no core hacks)

1. Subscribe our listener to **`core.viewtopic_get_post_data` with priority
   lower than -2** (e.g. `-10`), so it runs *after* their insert on the same
   request.
2. Gate hard:
   * `$_REQUEST['thanks']` present, `$_REQUEST['rthanks']` absent;
   * `gfksx/ThanksForPosts` extension is enabled (else the `thanks` table may
     not exist — check via the extension manager, never assume);
   * thanker ≠ post author (re-check, don't trust upstream);
   * thanker is a real registered user.
3. Confirm the row exists in `{prefix}thanks` for this (post, thanker) —
   protects against awarding on failed/duplicate inserts.
4. Award via the existing funnel:
   `Transact::award_by_trigger('thanks_received', $poster_id, $post_id,
   $forum_id, 'Thanks received')`.
5. Exactly-once: before awarding, check our ledger for an existing
   `(trigger_name='thanks_received', content_id=$post_id, note=$thanker_id)`
   row for the poster. (Refresh replays the GET; upstream dedupes their table
   but our award must dedupe itself.)
6. Removals (`rthanks`): **ignore in v1** (no clawback — matches how we treat
   post deletions). Revisit only if admins demand it.
7. Seed row (with the feature): `('thanks_received', primary_currency, 2.00,
   active)` — mirrors the XF twin. Ship the event as *inactive* until the
   admin enables ThanksForPosts? Better: active, but the handler no-ops when
   the extension is absent (zero queries beyond the enabled-check, cached).

## Verification plan (when built)

* Install ThanksForPosts in dev, thank a post as user B → author ledger
  `thanks_received`, balance +2.00.
* Refresh the `?thanks=` URL → no second award (ledger still 1 row).
* Self-thanks attempt → no award. Thanks extension disabled → no award,
  no errors.
* Remove thanks → ledger untouched (documented v1 behavior).

## Alternatives considered

* **Decorating their helper service** (`gfksx.ThanksForPosts.helper`):
  rejected — container decoration across extensions is brittle and breaks on
  their refactors.
* **Polling/cron sweep of the thanks table**: rejected — awards should land
  at action time, and cron is traffic-fired (see PHPBB-GOTCHAS.md §4).
* **Forking their extension**: rejected — maintenance burden, license
  friction (theirs is GPL-2.0).
