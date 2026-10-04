# OpenCredits for phpBB (dev stack)

phpBB 3.3 twin of the XenForo [OpenCredits](https://github.com/techwiz18/open-credits) addon (MIT).
No real-money features in v1.

## Layout

* `www/` — phpBB 3.3 runtime, gitignored, dev-only. You supply the zip.
* `ext/techwiz18/opencredits/` — the actual extension (committed).
* `docs/` — architecture, dev setup, gotchas.

## Quick start

```bash
cp ../phpBB-3.3.19.zip ../phpBB-3.3.19.zip  # any 3.3 full package in parent dir
./scripts/dev.sh up        # build + start web/db/phpmyadmin
./scripts/dev.sh unpack    # unzips ../phpBB-3.3*.zip into www/
# open http://localhost:8090/install — DB host: db, name: phpbb_dev, user: phpbb, pass: phpbbdev
```

* Forum: http://localhost:8090 · phpMyAdmin: http://localhost:8091 (root/rootpass)
* The extension source is bind-mounted live; enable it in ACP → Customise → Extensions.

See `docs/DEV.md`, `docs/ARCHITECTURE.md`, `docs/PHPBB-GOTCHAS.md`.
