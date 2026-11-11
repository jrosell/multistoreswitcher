#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

theme="${1:-hummingbird}"
if [[ "$theme" != hummingbird && "$theme" != classic ]]; then
  echo "Usage: $0 [hummingbird|classic]" >&2
  exit 2
fi
export TEST_SHOP_PORT="${TEST_SHOP_PORT:-8080}"
env_file=tests/fixtures/.env.test

if ! command -v docker >/dev/null || ! docker compose version >/dev/null 2>&1; then
  echo 'Docker Engine and Docker Compose v2 are required.' >&2
  exit 1
fi

if [[ ! -e "$env_file" ]]; then
  umask 077
  python3 - <<'PY'
import secrets
from pathlib import Path

with Path('tests/fixtures/.env.test').open('x') as env_file:
    for name in ('DEV_DB_ROOT_PASSWORD', 'DEV_DB_PASSWORD', 'DEV_ADMIN_PASSWORD'):
        env_file.write(f'{name}={secrets.token_hex(24)}\n')
PY
fi

compose=(docker compose --env-file "$env_file" -f tests/fixtures/docker-compose.yml)
"${compose[@]}" up -d

if ! "${compose[@]}" exec -T shop test -f /var/www/html/app/config/parameters.php 2>/dev/null; then
  ready=0
  for attempt in $(seq 1 180); do
    if "${compose[@]}" exec -T shop test -d /var/www/html/install 2>/dev/null \
      && "${compose[@]}" exec -T shop test ! -e /var/www/html/install.lock 2>/dev/null \
      && curl -sS --max-time 10 "http://localhost:${TEST_SHOP_PORT}/" -o /tmp/multistoreswitcher-test-shop.html; then
      ready=1
      break
    fi
    sleep 2
  done

  if [[ "$ready" != 1 ]]; then
    "${compose[@]}" logs --tail 80 shop
    echo 'PrestaShop files did not finish initializing.' >&2
    exit 1
  fi

  "${compose[@]}" exec -T --user www-data shop sh -c 'php -d memory_limit=-1 install/index_cli.php \
    --domain="$PS_DOMAIN" --db_server="$DB_SERVER:$DB_PORT" --db_name="$DB_NAME" --db_user="$DB_USER" \
    --db_password="$DB_PASSWD" --prefix="$DB_PREFIX" --firstname=Local --lastname=Developer \
    --password="$ADMIN_PASSWD" --email="$ADMIN_MAIL" --language="$PS_LANGUAGE" --country="$PS_COUNTRY" \
    --all_languages="$PS_ALL_LANGUAGES" --newsletter=0 --send_email=0 --ssl="$PS_ENABLE_SSL"'
  "${compose[@]}" exec -T shop rm -rf /var/www/html/install /var/www/html/install.lock
fi
"${compose[@]}" exec -T --user www-data shop php /opt/module-dev/fixture.php
"${compose[@]}" exec -T --user www-data shop php bin/console prestashop:theme:enable "$theme" --no-interaction
"${compose[@]}" exec -T --user www-data shop php /opt/module-dev/fixture.php

curl -fsSL --max-time 30 "http://localhost:${TEST_SHOP_PORT}/" -o /tmp/multistoreswitcher-test-shop.html
grep -q 'data-multistoreswitcher-responsive' /tmp/multistoreswitcher-test-shop.html
echo "Test shop ready: http://localhost:${TEST_SHOP_PORT}/ ($theme)"
