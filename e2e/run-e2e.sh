#!/usr/bin/env bash
#
# Runs the Hi.Events E2E suite against the hermetic docker stack.
# Works locally and in CI (the GitHub Actions workflow calls this script).
#
# Local default: REUSE the stack if it is already up and healthy (fast iteration);
# create it if not. The stack is left running afterwards. In CI ($CI set) the
# stack is always recreated from scratch and left up for log collection.
#
# Usage:
#   ./e2e/run-e2e.sh                     # reuse or create stack, run all tests
#   ./e2e/run-e2e.sh --fresh             # force a clean stack recreation first
#   ./e2e/run-e2e.sh --teardown          # tear the stack down after the run
#   ./e2e/run-e2e.sh --skip-stack        # don't manage the stack; run against whatever
#                                          is already up (e.g. the dev stack)
#   ./e2e/run-e2e.sh --skip-deps         # skip npm ci / browser install (fast re-runs)
#   ./e2e/run-e2e.sh -- --grep @smoke    # pass remaining args through to playwright
#
# NOTE: `docker compose up` never rebuilds images. After changing backend/ or
# frontend/ source, rebuild before running:
#   docker compose -f docker/e2e/docker-compose.e2e.yml build backend frontend

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="$REPO_ROOT/docker/e2e/docker-compose.e2e.yml"

if [ -f "$SCRIPT_DIR/.env" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in ''|\#*) continue ;; esac
    key="${line%%=*}"
    [ -n "$(printenv "$key")" ] && continue
    eval "export $line"
  done < "$SCRIPT_DIR/.env"
fi

FRESH="${FRESH:-0}"
TEARDOWN="${TEARDOWN:-0}"
SKIP_DEPS="${SKIP_DEPS:-0}"
SKIP_STACK="${SKIP_STACK:-0}"
PW_ARGS=()

while [ $# -gt 0 ]; do
  case "$1" in
    --fresh)      FRESH=1; shift ;;
    --teardown)   TEARDOWN=1; shift ;;
    --skip-stack) SKIP_STACK=1; shift ;;
    --skip-deps)  SKIP_DEPS=1; shift ;;
    --)           shift; PW_ARGS=("$@"); break ;;
    *)            PW_ARGS+=("$1"); shift ;;
  esac
done

if [ -n "${CI:-}" ]; then
  FRESH=1
  TEARDOWN=0
fi

compose() { docker compose -f "$COMPOSE_FILE" "$@"; }

BASE_URL="${E2E_BASE_URL:-http://localhost:8123}"
SA_EMAIL="${E2E_SUPERADMIN_EMAIL:-superadmin@e2e.test}"
SA_PASSWORD="${E2E_SUPERADMIN_PASSWORD:-SuperAdminPass123!}"

cleanup() {
  if [ "$SKIP_STACK" = "1" ]; then
    return
  fi
  if [ "$TEARDOWN" = "1" ]; then
    echo "==> Tearing down stack"
    compose down --remove-orphans -v
  else
    echo "==> Leaving stack up (base URL: $BASE_URL). Tear down with: ./e2e/run-e2e.sh --teardown, or docker compose -f docker/e2e/docker-compose.e2e.yml down -v"
  fi
}
trap cleanup EXIT

stack_is_healthy() {
  [ "$(compose ps --services --status running 2>/dev/null | wc -l | tr -d ' ')" -ge 6 ]
}

superadmin_exists() {
  curl -fsS -o /dev/null -X POST "$BASE_URL/api/auth/login" \
    -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d "{\"email\":\"$SA_EMAIL\",\"password\":\"$SA_PASSWORD\"}" 2>/dev/null
}

if [ "$SKIP_STACK" != "1" ]; then
  if [ "$FRESH" != "1" ] && stack_is_healthy; then
    echo "==> Reusing running stack (pass --fresh for a clean one; rebuild images first if backend/frontend source changed)"
  else
    echo "==> Recreating a clean stack"
    compose down --remove-orphans -v
    compose up -d --wait
  fi

  echo "==> Running migrations"
  compose exec -T backend php artisan migrate --force

  if superadmin_exists; then
    echo "==> Superadmin already provisioned"
  else
    echo "==> Provisioning superadmin"
    compose exec -T backend php artisan dev:bootstrap \
      --email="$SA_EMAIL" \
      --password="$SA_PASSWORD"
  fi
fi

if [ "$SKIP_DEPS" != "1" ]; then
  echo "==> Installing suite dependencies"
  ( cd "$SCRIPT_DIR" && npm ci )
  echo "==> Installing Playwright browsers"
  ( cd "$SCRIPT_DIR" && npx playwright install --with-deps chromium )
fi

echo "==> Running Playwright"
if [ ${#PW_ARGS[@]} -gt 0 ]; then
  ( cd "$SCRIPT_DIR" && npx playwright test "${PW_ARGS[@]}" )
else
  ( cd "$SCRIPT_DIR" && npx playwright test )
fi
