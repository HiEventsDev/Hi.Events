#!/bin/bash
# ============================================================
#  Hi.Events - Local Test Runner for Linux/Mac
#  Usage:
#    ./run-tests.sh              Run all tests (unit + feature)
#    ./run-tests.sh unit         Run only unit tests
#    ./run-tests.sh feature      Run only feature tests
#    ./run-tests.sh down         Stop and remove test containers
#    ./run-tests.sh reset        Reset test environment (clean rebuild)
# ============================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

TEST_SUITE="${1:-all}"

if [ "$TEST_SUITE" = "down" ]; then
    echo "Stopping test containers..."
    docker compose -f docker-compose.test.yml down -v
    echo "Done."
    exit 0
fi

if [ "$TEST_SUITE" = "reset" ]; then
    echo "Resetting test environment..."
    docker compose -f docker-compose.test.yml down -v
    docker compose -f docker-compose.test.yml build --no-cache
    echo "Reset complete. Run './run-tests.sh' to start tests."
    exit 0
fi

echo "============================================================"
echo " Hi.Events - Local Test Runner"
echo "============================================================"
echo ""

# Start services
echo "[1/4] Starting test infrastructure (PostgreSQL, Redis)..."
docker compose -f docker-compose.test.yml up -d pgsql redis

# Wait for services
echo "[2/4] Waiting for services to be ready..."
sleep 5

# Build and start test runner
echo "[3/4] Building test runner container..."
docker compose -f docker-compose.test.yml up -d --build test-runner

# Wait for container to be ready
sleep 3

# Run migrations
echo "[4/4] Running database migrations..."
docker compose -f docker-compose.test.yml exec test-runner php artisan migrate --force --quiet 2>/dev/null || true

# Run tests
echo ""
echo "============================================================"

TEST_EXIT=0
if [ "$TEST_SUITE" = "unit" ]; then
    echo " Running UNIT tests..."
    echo "============================================================"
    echo ""
    docker compose -f docker-compose.test.yml exec test-runner ./vendor/bin/phpunit tests/Unit --colors=always || TEST_EXIT=$?
elif [ "$TEST_SUITE" = "feature" ]; then
    echo " Running FEATURE tests..."
    echo "============================================================"
    echo ""
    docker compose -f docker-compose.test.yml exec test-runner ./vendor/bin/phpunit tests/Feature --colors=always || TEST_EXIT=$?
else
    echo " Running ALL tests..."
    echo "============================================================"
    echo ""
    docker compose -f docker-compose.test.yml exec test-runner ./vendor/bin/phpunit --colors=always || TEST_EXIT=$?
fi

echo ""
if [ $TEST_EXIT -eq 0 ]; then
    echo "============================================================"
    echo " ALL TESTS PASSED"
    echo "============================================================"
else
    echo "============================================================"
    echo " SOME TESTS FAILED"
    echo "============================================================"
fi

exit $TEST_EXIT
