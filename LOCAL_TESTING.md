# Local Testing Setup

This guide explains how to run the Hi.Events test suite locally using Docker.

## Prerequisites

- **Docker Desktop** installed and running
  - [Download for Windows](https://www.docker.com/products/docker-desktop/)
  - [Download for Mac](https://www.docker.com/products/docker-desktop/)
  - Linux: Install Docker Engine + Docker Compose

## Quick Start

### Windows

Open **Command Prompt** or **PowerShell**, navigate to the project root, and run:

```cmd
cd docker\testing
run-tests.bat
```

### Linux / macOS

```bash
cd docker/testing
./run-tests.sh
```

## Available Commands

| Command | Description |
|---------|-------------|
| `run-tests.bat` / `./run-tests.sh` | Run all tests (unit + feature) |
| `run-tests.bat unit` / `./run-tests.sh unit` | Run only unit tests |
| `run-tests.bat feature` / `./run-tests.sh feature` | Run only feature tests |
| `run-tests.bat down` / `./run-tests.sh down` | Stop and remove all test containers |
| `run-tests.bat reset` / `./run-tests.sh reset` | Full reset: remove containers and rebuild from scratch |

## What Happens When You Run Tests

1. **PostgreSQL 17** and **Redis 7** containers start up
2. A **PHP 8.4 test runner** container builds with all backend dependencies
3. Database migrations run automatically
4. PHPUnit executes the test suite
5. Results are displayed in the terminal

## Running Specific Test Files

If you want to run a single test file, use Docker directly:

```bash
# From the docker/testing directory
docker compose -f docker-compose.test.yml exec test-runner \
  ./vendor/bin/phpunit tests/Unit/Services/Domain/Order/OrderCancelServiceTest.php --colors=always
```

On Windows (Command Prompt):

```cmd
docker compose -f docker-compose.test.yml exec test-runner ./vendor/bin/phpunit tests/Unit/Services/Domain/Order/OrderCancelServiceTest.php --colors=always
```

## Running Tests with Filter

To run tests matching a specific name pattern:

```bash
docker compose -f docker-compose.test.yml exec test-runner \
  ./vendor/bin/phpunit --filter="testCancelOrder" --colors=always
```

## Architecture

The test setup uses three containers:

| Container | Image | Purpose | Port |
|-----------|-------|---------|------|
| `hi-events-test-runner` | PHP 8.4 CLI | Runs PHPUnit tests | - |
| `hi-events-test-pgsql` | PostgreSQL 17 | Test database | 5433 |
| `hi-events-test-redis` | Redis 7 | Cache (used by some tests) | 6380 |

Ports are mapped to non-default values (5433, 6380) to avoid conflicts with any local PostgreSQL or Redis installations.

## Troubleshooting

### "Docker is not running"
Make sure Docker Desktop is started. Look for the whale icon in your system tray (Windows) or menu bar (Mac).

### Port conflicts
If ports 5433 or 6380 are already in use, edit `docker-compose.test.yml` and change the host port mappings.

### Stale containers
If tests behave unexpectedly after code changes:

```bash
# Full reset
run-tests.bat reset   # Windows
./run-tests.sh reset  # Linux/Mac
```

### View container logs
```bash
docker compose -f docker-compose.test.yml logs test-runner
docker compose -f docker-compose.test.yml logs pgsql
```

## Cleanup

To stop all test containers and free up resources:

```bash
run-tests.bat down   # Windows
./run-tests.sh down  # Linux/Mac
```
