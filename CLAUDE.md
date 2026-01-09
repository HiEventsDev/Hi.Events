# Hi.Events - Project Overview for Claude Code

## Project Summary

**Hi.Events** is an open-source event ticketing and management platform designed as a modern alternative to Eventbrite and Tickettailor. It's a self-hosted, feature-rich solution that enables event organizers to sell tickets, manage attendees, and run events without per-ticket fees.

- **License**: AGPL-3.0
- **Repository**: https://github.com/HiEventsDev/hi.events
- **Tech Stack**: Laravel 12 (Backend) + React 18 + TypeScript (Frontend)
- **Architecture**: Monorepo with clear separation between backend and frontend

---

## Technology Stack

### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL 17
- **Cache/Queue**: Redis 7
- **Authentication**: Laravel Sanctum + JWT
- **Payments**: Stripe PHP SDK
- **Architecture**: Clean Architecture + DDD (Domain-Driven Design)
- **Testing**: PHPUnit + Mockery
- **Code Style**: Laravel Pint (PSR-12)

### Frontend
- **Framework**: React 18 + TypeScript 5
- **Build Tool**: Vite 5
- **UI Library**: Mantine 8
- **Routing**: React Router 7 with SSR support
- **State Management**: React Query (TanStack Query) for server state
- **Styling**: SCSS + Mantine CSS-in-JS
- **i18n**: Lingui
- **PDF**: @react-pdf/renderer

### Infrastructure
- **Container**: Docker (all-in-one, development, production configs)
- **Web Server**: Nginx + PHP-FPM
- **Process Manager**: Supervisor (queue workers)

---

## Project Structure

```
/Hi.Events (Monorepo)
├── /backend/              # Laravel API backend
│   ├── /app/
│   │   ├── /Http/Actions/      # 267 action-based controllers
│   │   ├── /Models/            # 48 Eloquent models
│   │   ├── /Services/          # 418+ service classes
│   │   │   ├── /Application/   # Use case orchestration
│   │   │   ├── /Domain/        # Core business logic (24 domains)
│   │   │   └── /Infrastructure/ # External integrations
│   │   ├── /DomainObjects/     # Enums, DTOs, Interfaces
│   │   ├── /Repository/        # Repository pattern implementations
│   │   ├── /Events/            # Domain events
│   │   └── /Listeners/         # Event listeners
│   ├── /database/migrations/   # 89 migrations
│   ├── /routes/api.php         # API routes (~32KB)
│   └── /config/                # 22 config files
│
├── /frontend/            # React + TypeScript frontend
│   ├── /src/
│   │   ├── /api/              # 27 API client modules
│   │   ├── /components/
│   │   │   ├── /common/       # 114+ reusable components
│   │   │   ├── /modals/       # 41+ modal components
│   │   │   └── /routes/       # Route-specific components
│   │   ├── /hooks/            # Custom React hooks
│   │   ├── /mutations/        # React Query mutations
│   │   ├── /queries/          # React Query queries
│   │   ├── /stores/           # Client state management
│   │   └── /locales/          # i18n translations
│   └── vite.config.ts
│
└── /docker/              # Docker configurations
    ├── /all-in-one/      # Complete stack
    ├── /development/     # Dev environment
    ├── /backend/         # Backend-only
    └── /frontend/        # Frontend-only
```

---

## Architecture Patterns

### Backend Architecture

#### 1. **Action-Based Controllers**
Each HTTP endpoint is handled by a single-action class in `/app/Http/Actions/`:

```php
// Example: CreateEventAction
class CreateEventAction
{
    public function __invoke(CreateEventRequest $request, CreateEventService $service)
    {
        $event = $service->create($request->validated());
        return new EventResource($event);
    }
}
```

**Key Points**:
- One action = One responsibility
- Located in `/backend/app/Http/Actions/`
- Named descriptively: `CreateEventAction`, `UpdateOrderAction`, `RefundOrderAction`
- Dependencies injected via constructor or method parameters

#### 2. **Layered Service Architecture**

Three service layers in `/app/Services/`:

- **Domain Services** (`/Services/Domain/`): Core business logic
  - Example: `Event/`, `Order/`, `Attendee/`, `Product/`, `PromoCode/`
  - Contains business rules and validation

- **Application Services** (`/Services/Application/`): Use case orchestration
  - Coordinates domain services
  - Manages transactions
  - Handles workflows

- **Infrastructure Services** (`/Services/Infrastructure/`): External integrations
  - Stripe payments
  - Email sending
  - File storage (S3)
  - Webhooks
  - VAT calculations

#### 3. **Repository Pattern**

All data access goes through repositories in `/app/Repository/`:

```php
interface EventRepositoryInterface
{
    public function findById(int $id): Event;
    public function create(array $data): Event;
}

class EventRepository implements EventRepositoryInterface { }
```

#### 4. **Domain-Driven Design**

- **Domain Objects** (`/app/DomainObjects/`): Enums, status objects, value objects
- **DTOs** (`/app/DataTransferObjects/`): Data transfer between layers
- **Events** (`/app/Events/`): Domain events for state changes
- **Value Objects** (`/app/Values/`): Immutable data objects

### Frontend Architecture

#### 1. **Server-Side Rendering (SSR)**

- Entry points: `entry.client.tsx` and `entry.server.tsx`
- React Router handles SSR with streaming support
- Better SEO and faster initial page loads

#### 2. **Data Fetching Pattern**

**React Query for server state**:

```typescript
// Query hook
const { data, isLoading } = useQuery({
  queryKey: ['events', eventId],
  queryFn: () => eventApi.getEvent(eventId)
});

// Mutation hook
const { mutate } = useMutation({
  mutationFn: (data) => eventApi.createEvent(data),
  onSuccess: () => queryClient.invalidateQueries(['events'])
});
```

**API Client Modules** (`/src/api/`):
- One module per domain: `events.ts`, `orders.ts`, `attendees.ts`
- Axios-based HTTP client
- Type-safe request/response interfaces

#### 3. **Component Organization**

```
/components/
├── /common/       # Reusable UI components
├── /forms/        # Form components
├── /layouts/      # Page layouts
├── /modals/       # Modal dialogs
└── /routes/       # Route-specific components
    ├── /admin/
    ├── /event/
    ├── /account/
    └── /organizer/
```

#### 4. **State Management**

- **Server State**: React Query (caching, synchronization)
- **Client State**: Custom stores in `/stores/`
- **Form State**: Mantine Form hooks

---

## Core Domain Models (48 Models)

### Key Entities

**Account & Users**:
- `Account` - Top-level tenant
- `User` - System users
- `AccountUser` - User-account relationships

**Organizations**:
- `Organizer` - Event organizers
- `OrganizerSetting` - Organizer configuration

**Events**:
- `Event` - Core event entity
- `EventSetting` - Event configuration
- `EventStatistic` - Real-time analytics
- `EventDailyStatistic` - Daily aggregates

**Products & Pricing**:
- `Product` - Ticket types and add-ons
- `ProductPrice` - Tiered pricing
- `ProductCategory` - Product grouping
- `ProductQuestion` - Custom attendee questions

**Orders & Sales**:
- `Order` - Customer orders
- `OrderItem` - Line items
- `OrderRefund` - Full/partial refunds
- `OrderPaymentPlatformFee` - Platform fees
- `OrderApplicationFee` - Application fees

**Attendees**:
- `Attendee` - Ticket holders
- `AttendeeCheckIn` - Check-in records
- `QuestionAnswer` - Custom question responses

**Promotions & Capacity**:
- `PromoCode` - Discount codes
- `TaxAndFee` - VAT and service fees
- `CapacityAssignment` - Shared capacity limits

**Payments**:
- `StripePayment` - Payment records
- `StripeCustomer` - Customer profiles
- `StripePayout` - Payouts to organizers
- `Invoice` - Order invoices

**Communication**:
- `Message` - Message templates
- `OutgoingMessage` - Sent messages
- `Email` - Email queue

**Integrations**:
- `Webhook` - Webhook configurations
- `WebhookLog` - Webhook delivery logs
- `Affiliate` - Affiliate tracking

---

## Key Features by Domain

### Ticketing & Sales
- Multiple ticket types (free, paid, donation, tiered)
- Promo codes with usage limits
- Product add-ons (merchandise)
- Capacity management (shared limits)
- Hidden tickets (promo-code only)
- VAT and service fee support
- Order management and refunds

### Attendee Management
- Custom checkout questions
- Advanced search/filtering
- CSV/XLSX export
- QR code check-in
- Attendee self-service portal
- Bulk messaging

### Event Management
- Multi-organizer support
- Event duplication
- Status management (draft, live, archived)
- Statistics and analytics
- Daily sales reports
- Tax reports

### Branding & Customization
- Custom PDF ticket designs
- Homepage builder (drag-and-drop)
- Embeddable ticket widget
- SEO settings
- Theme customization
- Email template editor (Liquid)

### Payment Processing
- Stripe integration
- Stripe Connect (instant payouts)
- Offline payments
- Invoice generation
- Platform fee configuration
- VAT handling

### Analytics & Reporting
- Real-time sales dashboard
- Revenue reports
- Tax reports
- Affiliate tracking
- Product performance
- Check-in statistics

### Webhooks & Integrations
- Event-driven webhooks
- Retry logic
- Logging and history
- Integration with Zapier, Make, etc.

---

## Development Workflow

### Backend Development

#### Starting the Backend

```bash
cd backend

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

#### Common Commands

```bash
# Run tests
php artisan test

# Code formatting
./vendor/bin/pint

# Queue worker (for jobs)
php artisan queue:work

# Clear cache
php artisan cache:clear
php artisan config:clear
```

#### Creating New Features

1. **Create Action Class**: `/app/Http/Actions/SomeAction.php`
2. **Create Service**: `/app/Services/Domain/SomeService.php`
3. **Add Route**: `/routes/api.php`
4. **Create Request Validator**: `/app/Http/Request/SomeRequest.php`
5. **Create Resource**: `/app/Http/Resources/SomeResource.php`
6. **Add Tests**: `/tests/Feature/SomeTest.php`

### Frontend Development

#### Starting the Frontend

```bash
cd frontend

# Install dependencies
npm install

# Start dev server with SSR
npm run dev:ssr

# Build for production
npm run build
```

#### Common Commands

```bash
# Type checking
npm run typecheck

# Linting
npm run lint

# Extract i18n strings
npm run extract

# Compile translations
npm run compile
```

#### Creating New Features

1. **Create API Client**: `/src/api/someApi.ts`
2. **Create Query Hooks**: `/src/queries/useSomeQuery.ts`
3. **Create Mutation Hooks**: `/src/mutations/useSomeMutation.ts`
4. **Create Components**: `/src/components/routes/some/SomeComponent.tsx`
5. **Add Route**: Update React Router configuration

### Docker Development

```bash
# All-in-one (quick start)
cd docker/all-in-one
docker compose up -d

# Development (separate services)
cd docker/development
docker compose up -d

# View logs
docker compose logs -f

# Shell access
docker compose exec backend bash
docker compose exec frontend sh
```

---

## Database Management

### Migrations

Located in `/backend/database/migrations/` (89 migrations)

```bash
# Run migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Create new migration
php artisan make:migration create_some_table

# Fresh migration (WARNING: destroys data)
php artisan migrate:fresh
```

### Seeders

```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=SomeSeeder
```

---

## API Structure

### Authentication

All API requests require authentication via:
- **Bearer Token**: JWT via Laravel Sanctum
- **Header**: `Authorization: Bearer {token}`

### API Routes Pattern

Located in `/backend/routes/api.php`:

```php
// Pattern: /api/{version}/{resource}/{action}
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/events', CreateEventAction::class);
    Route::get('/events/{id}', GetEventAction::class);
    Route::patch('/events/{id}', UpdateEventAction::class);
    Route::delete('/events/{id}', DeleteEventAction::class);
});
```

### Response Format

```json
{
  "data": { ... },          // Resource data
  "meta": { ... },          // Metadata (pagination, etc.)
  "message": "Success",     // Optional message
  "errors": [ ... ]         // Validation errors (if any)
}
```

---

## Important Conventions

### Backend

1. **Action Classes**: One action per file, single `__invoke()` method
2. **Service Classes**: Descriptive names, single responsibility
3. **DTOs**: Use Spatie Laravel Data for type-safe data transfer
4. **Repository Methods**: `findById()`, `create()`, `update()`, `delete()`
5. **Enums**: Use for status and type fields (e.g., `EventStatus`, `OrderStatus`)
6. **Domain Events**: Dispatch for significant state changes
7. **Transactions**: Wrap multi-step operations in DB transactions

### Frontend

1. **File Naming**: PascalCase for components, camelCase for utilities
2. **Component Structure**: Functional components with TypeScript
3. **Hooks**: Prefix with `use` (e.g., `useEvent`, `useOrder`)
4. **API Calls**: Always through API client modules
5. **Error Handling**: Use React Query's error states
6. **Translations**: Use Lingui's `<Trans>` component or `t()` macro
7. **Styling**: Prefer Mantine components over custom CSS

### Testing

1. **Backend**: Feature tests for actions, unit tests for services
2. **Coverage**: Aim for critical paths (orders, payments, refunds)
3. **Mocking**: Use Mockery for external services (Stripe, email)
4. **Naming**: `test_it_does_something()` format

---

## Environment Configuration

### Backend `.env` (Key Variables)

```bash
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hi_events
DB_USERNAME=postgres
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Stripe
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Email
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025

# Storage
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

# Frontend URL
FRONTEND_URL=http://localhost:5678
```

### Frontend `.env` (Key Variables)

```bash
# API URL
VITE_API_URL_CLIENT=http://localhost:8000
VITE_API_URL_SERVER=http://backend:8000

# Stripe
VITE_STRIPE_PUBLISHABLE_KEY=pk_test_...

# Feature Flags
VITE_ENABLE_SSR=true
```

---

## Common Tasks

### Creating a New Event

1. User creates event via frontend
2. `CreateEventAction` validates request
3. `CreateEventService` orchestrates:
   - Creates `Event` model
   - Creates `EventSetting` with defaults
   - Creates `EventStatistic` record
   - Dispatches `EventCreated` event
4. Listeners handle post-creation tasks

### Processing an Order

1. Customer submits order via checkout widget
2. `CreateOrderAction` validates
3. `CreateOrderService`:
   - Validates promo codes
   - Checks capacity
   - Calculates fees/taxes
   - Creates `Order` and `OrderItem`s
   - Initiates Stripe payment
4. Webhook confirms payment
5. `CompleteOrderService`:
   - Marks order as paid
   - Creates `Attendee` records
   - Sends confirmation emails
   - Updates statistics

### Checking In an Attendee

1. Scanner scans QR code
2. `CheckInAttendeeAction` receives attendee ID
3. `CheckInService`:
   - Validates attendee exists
   - Checks not already checked in
   - Creates `AttendeeCheckIn` record
   - Returns check-in status

### Issuing a Refund

1. Organizer initiates refund
2. `RefundOrderAction` validates
3. `RefundOrderService`:
   - Calculates refundable amount
   - Processes Stripe refund
   - Creates `OrderRefund` record
   - Updates attendee statuses
   - Sends refund emails
   - Updates statistics

---

## Troubleshooting

### Common Backend Issues

**Database connection errors**:
```bash
# Check PostgreSQL is running
docker compose ps

# Reset database
php artisan migrate:fresh --seed
```

**Queue jobs not processing**:
```bash
# Start queue worker
php artisan queue:work

# Check failed jobs
php artisan queue:failed
```

**Cache issues**:
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Common Frontend Issues

**API connection errors**:
- Check `VITE_API_URL_CLIENT` in `.env`
- Verify backend is running
- Check CORS configuration

**SSR not working**:
```bash
# Rebuild SSR bundle
npm run build
npm run dev:ssr
```

**Translation issues**:
```bash
# Re-extract and compile
npm run extract
npm run compile
```

---

## Key Files to Know

### Backend

- `/backend/app/Http/Actions/` - All API endpoints (267 actions)
- `/backend/app/Services/Domain/` - Core business logic (24 domains)
- `/backend/app/Models/` - Eloquent models (48 models)
- `/backend/routes/api.php` - API route definitions
- `/backend/config/app.php` - Application configuration
- `/backend/database/migrations/` - Database schema (89 migrations)

### Frontend

- `/frontend/src/api/` - API client modules (27 modules)
- `/frontend/src/components/routes/` - Route components
- `/frontend/src/components/common/` - Reusable components (114+)
- `/frontend/src/queries/` - React Query hooks
- `/frontend/src/mutations/` - React Query mutations
- `/frontend/vite.config.ts` - Build configuration
- `/frontend/src/entry.client.tsx` - Browser entry
- `/frontend/src/entry.server.tsx` - SSR entry

### Docker

- `/docker/all-in-one/docker-compose.yml` - Quick start stack
- `/docker/development/docker-compose.yml` - Dev environment
- `/docker/all-in-one/Dockerfile` - Production image

---

## Testing Strategy

### Backend Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=CreateEventTest

# With coverage
php artisan test --coverage
```

**Test Structure**:
- `/tests/Feature/` - API endpoint tests
- `/tests/Unit/` - Service and utility tests
- Use factories for test data
- Mock external services (Stripe, email)

### Frontend Testing

```bash
# Run tests
npm test

# Type checking
npm run typecheck
```

---

## Deployment

### Docker All-in-One

```bash
cd docker/all-in-one

# Build image
docker compose build

# Start services
docker compose up -d

# Check logs
docker compose logs -f

# Access at http://localhost:8123
```

### Manual Deployment

**Backend**:
1. Clone repository
2. Install dependencies: `composer install --no-dev`
3. Configure `.env`
4. Run migrations: `php artisan migrate`
5. Configure web server (Nginx + PHP-FPM)
6. Start queue worker: `php artisan queue:work`

**Frontend**:
1. Install dependencies: `npm install`
2. Configure `.env`
3. Build: `npm run build`
4. Serve `dist/` with Node.js: `npm run start`

---

## Additional Resources

- **Documentation**: Check `/docs/` folder (if exists)
- **GitHub**: https://github.com/HiEventsDev/hi.events
- **Issues**: https://github.com/HiEventsDev/hi.events/issues
- **License**: AGPL-3.0 (see LICENSE file)

---

## Quick Reference

### Most Important Directories

```bash
# Backend
backend/app/Http/Actions/          # API endpoints
backend/app/Services/Domain/       # Business logic
backend/app/Models/                # Database models
backend/routes/api.php             # Route definitions

# Frontend
frontend/src/components/routes/    # Page components
frontend/src/api/                  # API clients
frontend/src/queries/              # Data fetching
frontend/src/mutations/            # Data mutations
```

### Most Common Commands

```bash
# Backend
php artisan migrate                # Run migrations
php artisan test                   # Run tests
php artisan queue:work             # Start worker
./vendor/bin/pint                  # Format code

# Frontend
npm run dev:ssr                    # Dev server
npm run build                      # Production build
npm run typecheck                  # Type checking
npm run lint                       # Linting

# Docker
docker compose up -d               # Start services
docker compose logs -f             # View logs
docker compose exec backend bash   # Shell access
```

---

## Notes for Claude Code

- This is a **production-grade application** with complex business logic
- Always read existing code before making changes
- Follow the established patterns (Actions, Services, Repositories)
- Test payment flows carefully (use Stripe test mode)
- Be cautious with refund logic (financial implications)
- Respect the layered architecture (don't bypass services)
- Use TypeScript types consistently in frontend
- Maintain SSR compatibility in React components
- Keep translations up to date (run `npm run extract` after text changes)

---

**Last Updated**: 2026-01-09
