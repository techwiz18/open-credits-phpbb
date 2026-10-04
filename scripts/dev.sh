#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

cmd="${1:-help}"
ZIP=$(ls ../phpBB-3.3*.zip 2>/dev/null | head -n 1 || true)

case "$cmd" in
  up)
    cp -n .env.example .env || true
    docker compose up -d --build
    docker compose ps
    ;;
  down)
    docker compose down
    ;;
  unpack)
    if [ -z "$ZIP" ]; then echo "No phpBB-3.3*.zip found in parent dir"; exit 1; fi
    mkdir -p www
    # Docker creates bind-mount dirs as root; fix ownership from inside the
    # container (runs as root) so the host user can copy files.
    docker compose exec -T web chown -R 1000:1000 /var/www/html || true
    echo "Unpacking $ZIP -> www/"
    rm -rf tmp_unpack && mkdir -p tmp_unpack
    unzip -o "$ZIP" -d tmp_unpack >/dev/null
    # Full package wraps everything in phpBB3/
    if [ -d tmp_unpack/phpBB3 ]; then
      cp -r tmp_unpack/phpBB3/. www/
    else
      cp -r tmp_unpack/. www/
      rm -f www/*.zip
    fi
    rm -rf tmp_unpack
    ls www/ | head
    ;;
  logs)
    docker compose logs -f "${2:-web}"
    ;;
  fix-perms)
    # Apache runs as www-data; the installer needs these writable (dev-only 0777).
    docker compose exec -T web bash -c "chmod 0777 config.php && chmod -R 0777 cache store files images/avatars/upload"
    ;;
  link-ext)
    # ext/ is bind-mounted into the web container (see docker-compose.yml).
    docker compose exec web ls -la /var/www/html/ext/techwiz18/opencredits/
    ;;
  *)
    echo "usage: dev.sh {up|down|unpack|logs|link-ext}"
    ;;
esac
