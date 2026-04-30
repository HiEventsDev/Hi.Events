# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Hi.Events is an open-source event management and ticketing platform with a Laravel backend and React frontend, using Domain-Driven Design (DDD).

## Key Commands

### Backend (Laravel)

Commands must be executed in the `backend` docker container:

```bash
cd docker/development

docker compose -f docker-compose.dev.yml exec backend php artisan migrate
docker compose -f docker-compose.dev.yml exec backend php artisan generate-domain-objects
docker compose -f docker-compose.dev.yml exec backend php artisan test
docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=TestName
docker compose -f docker-compose.dev.yml exec backend php artisan test --testsuite=Unit
docker compose -f docker-compose.dev.yml exec backend ./vendor/bin/pint --test
```

### Frontend (React + Vite) - SSR Only

```bash
cd frontend
yarn install
yarn dev:ssr              # Development server
yarn build                # SSR build
yarn messages:extract     # Extract translatable strings
yarn messages:compile     # Compile translations
npx tsc --noEmit          # TypeScript validation
```

### Docker Development

```bash
cd docker/development
./start-dev.sh                     # Unsigned SSL certs
./start-dev.sh --certs=signed      # Signed certs with mkcert
```

## Development Guidelines

### Backend

#### Architecture Flow
- Request flow: **Action → Handler → Domain Service → Repository**
- Handlers can use repositories directly when a service would be overkill
- No Eloquent in handlers or services — Eloquent belongs in repositories only
- Favour composition over inheritance
- Keep code clean, but don't be dogmatic about it

#### General Standards
- **ALWAYS** wrap all translatable strings in `__()` helper
- Domain Objects are auto-generated via `php artisan generate-domain-objects` - never edit manually
- **Always** create unit tests for new features in `backend/tests/Unit/`
- **DON'T** add comments unless absolutely necessary
- **ALWAYS** sanitize user-provided content with `HtmlPurifierService` before storing, especially content rendered as HTML

#### DTOs
- Use Spatie Laravel Data package for all new DTOs
- **ALWAYS** extend `BaseDataObject`, not `BaseDTO`
- **ALWAYS** favor DTOs over arrays when returning multiple values from services

#### HTTP Actions
- Always extend `BaseAction.php`
- **ALWAYS** use BaseAction response methods: `resourceResponse()`, `jsonResponse()`, `errorResponse()`, `deletedResponse()`, etc. Never use `response()->json()` or `new JsonResponse()` directly
- Always use `isActionAuthorized` for non-public endpoints
- **DON'T** create actions handling multiple entity types with optional parameters - create separate, focused actions instead
- **DO** use base classes to share common validation and logic

#### Exception Handling
- **DON'T** use generic exceptions like `InvalidArgumentException` and `RuntimeException`
- **DO** use custom exceptions (e.g., `EmailTemplateValidationException`, `ResourceConflictException`)
- **DO** catch custom exceptions in actions and convert to `ValidationException::withMessages()` or appropriate error responses

#### Repository Pattern
- Favour existing repository methods over creating bespoke ones. E.g., use `findFirstWhere(['event_id' => $eventId])` instead of creating `findByEventId`

#### Database & Migrations
- **DO** use auto-incrementing integer IDs (`$table->id()`), not UUIDs
- Use anonymous class syntax for migrations

#### Enums
- Status enums go in `backend/app/DomainObjects/Status/`
- Other enums go in `backend/app/DomainObjects/Enums/`

#### Testing
- **DON'T** use `RefreshDatabase` - use `DatabaseTransactions` instead
- Unit tests extend Laravel's TestCase, not PHPUnit's TestCase
- Use Mockery for mocking

### Frontend

#### General Standards
- This is a SSR app - ensure safe usage of `window` and `document` objects
- Favour using existing components over creating new ones
- **DON'T** include unnecessary React imports
- **ALWAYS** add translations when adding new user-facing strings - use Lingui `t` function or `Trans` component
- **IMMEDIATELY** after adding translatable strings, add translations for all supported languages (use `/translations` skill for the workflow)

#### Data Fetching
- Use React Query for all API interactions
- Query example: `frontend/src/queries/useGetCapacityAssignment.ts`
- Mutation example: `frontend/src/mutations/useCreateAffiliate.ts`

#### UI & Styling
- Use Mantine UI components for UI elements
- Prefer SCSS modules over Mantine layout components for layout styling

#### Error Handling
- **DON'T** use `showNotification` from `@mantine/notifications`
- **DO** use `showSuccess`, `showError` from `frontend/src/utilites/notifications.tsx`
- **DO** use `useFormErrorResponseHandler` from `frontend/src/hooks/useFormErrorResponseHandler.tsx` for validation errors
- **DO** handle errors in parent components, not in reusable components

## Development Workflows

### Database Changes (in Backend Container)
1. Create migration: `php artisan make:migration create_XXX_table`
2. Run migration: `php artisan migrate`
3. Regenerate Domain Objects: `php artisan generate-domain-objects`

### Before Finalizing Changes
1. Frontend: `cd frontend && npx tsc --noEmit`
2. Backend: `docker compose -f docker-compose.dev.yml exec backend php artisan test --testsuite=Unit`
