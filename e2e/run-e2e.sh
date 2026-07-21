#!/usr/bin/env bash
#
# Runs the Hi.Events E2E suite against the hermetic docker stack.
# Works locally and in CI (the GitHub Actions workflow calls this script).
#
# Usage:
#   ./e2e/run-e2e.sh                     # (re)create stack, run all tests, tear down
#   ./e2e/run-e2e.sh --keep-stack        # leave the stack running afterwards
#   ./e2e/run-e2e.sh --skip-stack        # don't manage the stack; run against whatever
#                                          is already up (e.g. the dev stack)
#   ./e2e/run-e2e.sh --skip-deps         # skip npm ci / browser install (fast re-runs)
#   ./e2e/run-e2e.sh -- --grep @smoke    # pass remaining args through to playwright
#
# In CI ($CI set) the stack is always left up so the workflow can collect logs
# and artifacts before tearing it down itself.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="$REPO_ROOT/docker/e2e/docker-compose.e2e.yml"

KEEP_STACK="${KEEP_STACK:-0}"
SKIP_DEPS="${SKIP_DEPS:-0}"
SKIP_STACK="${SKIP_STACK:-0}"
PW_ARGS=()

while [ $# -gt 0 ]; do
  case "$1" in
    --keep-stack) KEEP_STACK=1; shift ;;
    --skip-stack) SKIP_STACK=1; shift ;;
    --skip-deps)  SKIP_DEPS=1; shift ;;
    --)           shift; PW_ARGS=("$@"); break ;;
    *)            PW_ARGS+=("$1"); shift ;;
  esac
done

if [ -n "${CI:-}" ]; then
  KEEP_STACK=1
fi

compose() { docker compose -f "$COMPOSE_FILE" "$@"; }

cleanup() {
  if [ "$SKIP_STACK" = "1" ]; then
    return
  fi
  if [ "$KEEP_STACK" != "1" ]; then
    echo "==> Tearing down stack"
    compose down --remove-orphans -v
  else
    echo "==> Leaving stack up (base URL: ${E2E_BASE_URL:-http://localhost:8123})"
  fi
}
trap cleanup EXIT

if [ "$SKIP_STACK" != "1" ]; then
  echo "==> Recreating a clean stack"
  compose down --remove-orphans -v
  compose up -d --wait

  echo "==> Running migrations"
  compose exec -T backend php artisan migrate --force
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
