---
paths:
  - "backend/tests/**"
  - "backend/phpunit.xml"
  - "backend/database/factories/**"
---

# Backend test setup

- CI runs `--testsuite=Unit` with no database, so a Unit test that reaches a query passes locally and fails in CI.
- `Account::factory()->create()` does not create the `account_users` row; tests that insert events need `User::factory()->withAccount()->create()`.
- Never hardcode a production algorithm's output (hashes, signatures, derived ids); compute the expected value through the same service.
- A pgsql volume older than `docker/development/pgsql-init/` has no `hievents_test` database; create it once: `docker compose -f docker-compose.dev.yml exec pgsql psql -U username -d backend -c 'CREATE DATABASE hievents_test OWNER username;'`
