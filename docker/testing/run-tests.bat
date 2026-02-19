@echo off
REM ============================================================
REM  Hi.Events - Local Test Runner for Windows
REM  Usage:
REM    run-tests.bat              Run all tests (unit + feature)
REM    run-tests.bat unit         Run only unit tests
REM    run-tests.bat feature      Run only feature tests
REM    run-tests.bat down         Stop and remove test containers
REM    run-tests.bat reset        Reset test environment (clean rebuild)
REM ============================================================

cd /d "%~dp0"

SET TEST_SUITE=%1

IF "%TEST_SUITE%"=="down" (
    echo Stopping test containers...
    docker compose -f docker-compose.test.yml down -v
    echo Done.
    exit /b 0
)

IF "%TEST_SUITE%"=="reset" (
    echo Resetting test environment...
    docker compose -f docker-compose.test.yml down -v
    docker compose -f docker-compose.test.yml build --no-cache
    echo Reset complete. Run 'run-tests.bat' to start tests.
    exit /b 0
)

echo ============================================================
echo  Hi.Events - Local Test Runner
echo ============================================================
echo.

REM Start services
echo [1/4] Starting test infrastructure (PostgreSQL, Redis)...
docker compose -f docker-compose.test.yml up -d pgsql redis
IF ERRORLEVEL 1 (
    echo ERROR: Failed to start infrastructure services.
    echo Make sure Docker Desktop is running.
    exit /b 1
)

REM Wait for services
echo [2/4] Waiting for services to be ready...
timeout /t 5 /nobreak >nul

REM Build and start test runner
echo [3/4] Building test runner container...
docker compose -f docker-compose.test.yml up -d --build test-runner
IF ERRORLEVEL 1 (
    echo ERROR: Failed to build test runner.
    exit /b 1
)

REM Wait for container to be ready
timeout /t 3 /nobreak >nul

REM Run migrations
echo [4/4] Running database migrations...
docker compose -f docker-compose.test.yml exec test-runner php artisan migrate --force --quiet 2>nul

REM Run tests
echo.
echo ============================================================

IF "%TEST_SUITE%"=="unit" (
    echo  Running UNIT tests...
    echo ============================================================
    echo.
    docker compose -f docker-compose.test.yml exec test-runner ./vendor/bin/phpunit tests/Unit --colors=always
) ELSE IF "%TEST_SUITE%"=="feature" (
    echo  Running FEATURE tests...
    echo ============================================================
    echo.
    docker compose -f docker-compose.test.yml exec test-runner ./vendor/bin/phpunit tests/Feature --colors=always
) ELSE (
    echo  Running ALL tests...
    echo ============================================================
    echo.
    docker compose -f docker-compose.test.yml exec test-runner ./vendor/bin/phpunit --colors=always
)

SET TEST_EXIT=%ERRORLEVEL%
echo.

IF %TEST_EXIT%==0 (
    echo ============================================================
    echo  ALL TESTS PASSED
    echo ============================================================
) ELSE (
    echo ============================================================
    echo  SOME TESTS FAILED
    echo ============================================================
)

exit /b %TEST_EXIT%
