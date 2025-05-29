# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Hi.Events is an open-source event management and ticketing platform with a Laravel backend and React frontend. The application uses a Domain-Driven Design (DDD) architecture with clear separation between application handlers, domain services, and infrastructure services.

## Key Commands

### Backend (Laravel)

Commands should be executed in the `backend` docker container.

```bash
# Navigate to backend directory first
cd backend

# Install dependencies
composer install

# Run database migrations
php artisan migrate

# Generate/update domain objects after schema changes
php artisan generate-domain-objects

# Run tests
php artisan test
php artisan test --filter=TestName  # Run specific test

# Translation extraction
php artisan langscanner
```

### Frontend (React + Vite) - SSR Only
```bash
# Navigate to frontend directory first
cd frontend

# Install dependencies
yarn install

# Development server (SSR)
yarn dev:ssr

# Build
yarn build          # SSR build (extracts translations first)

# Build SSR components separately
yarn build:ssr:client
yarn build:ssr:server

# Start production server
yarn start

# Linting
yarn lint

# Translation management
yarn messages:extract    # Extract translatable strings
yarn messages:compile    # Compile translations
cd scripts && ./list_untranslated_strings.sh  # List untranslated strings
```

### Docker Development
```bash
# Quick start from docker/development directory
cd docker/development
./start-dev.sh              # Generate unsigned SSL certs
./start-dev.sh --certs=signed  # Generate signed certs with mkcert

# Running commands in containers
cd docker/development
docker-compose -f docker-compose.dev.yml exec backend php artisan migrate
docker-compose -f docker-compose.dev.yml exec backend php artisan test

# All-in-one deployment from docker/all-in-one directory
cd docker/all-in-one
docker compose up -d
```

## Architecture Overview

### Backend Structure (Domain-Driven Design)

1. **Controllers/Actions** (`backend/app/Http/Actions/`): Entry points for HTTP requests, minimal logic
2. **Application Handlers** (`backend/app/Services/Application/Handlers/`): Orchestrate domain services, handle DTOs
3. **Domain Services** (`backend/app/Services/Domain/`): Core business logic, domain-specific operations
4. **Infrastructure Services** (`backend/app/Services/Infrastructure/`): External integrations (Stripe, email, etc.)
5. **Domain Objects** (`backend/app/DomainObjects/`): Auto-generated from database schema, read-only data representations
6. **Models** (`backend/app/Models/`): Eloquent models for database operations
7. **Repositories** (`backend/app/Repository/`): Data access layer with interfaces

### Frontend Structure (SSR)

- **API Clients** (`frontend/src/api/`): Typed API client methods
- **Components** (`frontend/src/components/`): Reusable UI components
- **Routes** (`frontend/src/components/routes/`): Page-level components
- **Queries/Mutations** (`frontend/src/queries/`, `frontend/src/mutations/`): React Query hooks for data fetching/updates
- **Layouts** (`frontend/src/components/layouts/`): Layout wrappers (Event, Organizer, Auth, etc.)
- **Entry Points**: `entry.server.tsx` and `entry.client.tsx` for SSR setup

### Key Architectural Patterns

1. **DTO Pattern**: Use Spatie Laravel Data package for all DTOs
2. **Repository Pattern**: All database access through repository interfaces
3. **Service Layer**: Business logic separated into domain and application services
4. **Handler Pattern**: Each use case has a dedicated handler class
5. **SSR Architecture**: Frontend uses server-side rendering exclusively

## Development Guidelines

### Backend
- Follow PSR-12 coding standards
- Use Spatie Laravel Data package for all new DTOs
- Wrap all translatable strings in `__()` helper
- Create repositories with interfaces for data access
- Domain Objects are auto-generated - do not edit manually
- Migrations should only contain schema changes, no logic

### Frontend
- Use Lingui for translations (`t` function or `Trans` component)
- Follow Airbnb JavaScript/React style guide
- Components should be functional with hooks
- Use React Query for all API interactions
- Maintain TypeScript types for all API responses
- All routing and rendering is server-side (SSR)

### Database Changes
1. Create migration: `php artisan make:migration create_XXX_table`
2. Run migration: `php artisan migrate`
3. Update Domain Objects: `php artisan generate-domain-objects`

### Testing
- Backend: PHPUnit tests in `backend/tests/`
- Run specific test: `php artisan test --filter=TestName`

### Payment Processing
- Stripe integration for payments
- Stripe Connect for organizer payouts
- Webhook handling for payment events

### Multi-tenancy
- Account-based multi-tenancy
- Each account can have multiple organizers
- Events belong to organizers
- Role-based access control (RBAC)

### Translations
- Backend: Laravel's translation system with `backend/lang/` files
- Frontend: Lingui with `frontend/src/locales/` files
- Supported languages: English, German, Spanish, Portuguese, French, Italian, Dutch, Chinese, Japanese, Vietnamese, Chinese (Traditional HK)
