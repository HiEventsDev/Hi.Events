# Hi.Events - AI Context

## Project Overview
Hi.Events is an open-source event ticketing and management platform. It allows organizers to sell tickets (free, paid, tiered), manage attendees, customize event pages, use promo codes, and handle features like check-in and analytics. It can be self-hosted (via Docker) or managed on the cloud.

## Tech Stack
- **Backend**: PHP 8.2+, Laravel 12.0
- **Frontend**: Node.js, React 18, Vite, React Router 7, @tanstack/react-query, Mantine UI, TipTap (Rich Text), Lingui (i18n).
- **Database/ORM**: Doctrine DBAL, Eloquent ORM.
- **Dependency Managers**: Composer (Backend), Yarn/NPM (Frontend).
- **Deployment**: Docker, docker-compose.

## Naming Conventions
- **Backend (PHP)**: Standard PSR-12 and Laravel conventions. `PascalCase` for classes (Models, Controllers, Services), `camelCase` for methods/variables, `snake_case` for database columns/tables.
- **Frontend (TS/TSX)**: `PascalCase` for React components (`Component.tsx`) and interfaces. `camelCase` for variables, hooks (`useHook.ts`), and utility functions.
- **Folders**: Lowercase directory names in the frontend (`components`, `mutations`). `PascalCase` directory names in the backend `app/` layer reflecting their architecture concepts (`Services`, `DomainObjects`).

## Key Directories & Architecture
### Backend (`/backend`)
Follows a layered/Domain-Driven Design architecture built on top of Laravel:
- `app/Http/`: API Controllers, Requests, and Middleware.
- `app/DomainObjects/`: Core domain entities holding business logic mapping.
- `app/DataTransferObjects/`: DTOs passing structured data between layers.
- `app/Services/`: Core business logic and use cases.
- `app/Repository/`: Data access layer (Repository Pattern) abstracting DB operations.
- `app/Models/`: Eloquent ORM models.
- `routes/`: API endpoint definitions.
- `database/`: Migrations, seeders, and factories.
- `tests/`: PHPUnit testing suite.

### Frontend (`/frontend`)
Component-based React application:
- `src/components/`: Reusable React UI components and layouts.
- `src/queries/` & `src/mutations/`: React Query hooks for fetching and modifying data via API.
- `src/api/`: Base API client configuration and wrappers.
- `src/router.tsx`: React Router configuration and route structure.
- `src/stores/`: Global state management.
- `src/locales/`: Internationalization (i18n) setup.

## Entry Points
- **Backend API**: `backend/public/index.php` handles web requests. `backend/artisan` handles CLI tasks.
- **Frontend Web**: `frontend/src/entry.client.tsx` (Client) and `frontend/src/entry.server.tsx` (SSR). Routed via `frontend/server.js` or Vite dev server.

## Standard Procedures
- **Run Locally (Docker All-in-One)**: 
  `cd docker/all-in-one`
  `docker compose up -d`
- **Run Frontend Dev**: 
  `cd frontend`
  `npm run dev:csr` (or `dev:ssr`)
- **Build Frontend**: 
  `cd frontend && npm run build`
- **Testing (Backend)**: 
  `cd backend && php artisan test` (or `vendor/bin/phpunit`)
