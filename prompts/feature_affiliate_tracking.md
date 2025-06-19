# Affiliate Tracking Feature Documentation

## Feature Overview

The affiliate tracking feature allows event organizers to create and manage affiliates who can promote events and earn commissions on ticket sales. Affiliates receive unique tracking codes that can be appended to event URLs to track sales attribution.

### Key Capabilities
- Create and manage affiliates with unique tracking codes
- Track sales and revenue generated through each affiliate
- Apply affiliate codes via URL parameters (invisible to customers)
- View affiliate performance metrics in the event dashboard
- Affiliate codes persist for 30 days via localStorage

## Database Schema

### Affiliates Table
Location: `backend/database/migrations/2025_06_18_033649_recreate_affiliates_table.php`

```sql
affiliates
- id (bigint, primary key)
- event_id (bigint, foreign key to events)
- account_id (bigint, foreign key to accounts)
- name (varchar 255, required)
- code (varchar 50, required, unique per event)
- email (varchar 255, nullable)
- total_sales (integer, default 0)
- total_sales_gross (decimal 10,2, default 0.00)
- status (enum: 'active', 'inactive', default 'active')
- created_at (timestamp)
- updated_at (timestamp)

Indexes:
- event_id_code (unique composite index)
- event_id (index)
- account_id (index)
- code (index)
- status (index)
```

### Orders Table Modification
- Added `affiliate_id` (bigint, nullable, foreign key to affiliates)
- Added index on `affiliate_id`

## Backend Implementation

### Models
- **Location**: `backend/app/Models/Affiliate.php`
- **Domain Object**: Auto-generated via `php artisan generate-domain-objects`
- **Relationships**: 
  - BelongsTo: Event, Account
  - HasMany: Orders

### Repository Pattern
- **Interface**: `backend/app/Repository/Interfaces/AffiliateRepositoryInterface.php`
- **Implementation**: `backend/app/Repository/Eloquent/AffiliateRepository.php`
- **Registration**: Added in `backend/app/Providers/RepositoryServiceProvider.php`

### Key Repository Methods
```php
- findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
- findByCodeAndEventId(string $code, int $eventId): ?AffiliateDomainObject // Only returns ACTIVE affiliates
- incrementSales(int $affiliateId, float $amount): void
```

### API Endpoints & Actions
All endpoints require authentication and event access authorization.

#### Create Affiliate
- **Endpoint**: `POST /events/{eventId}/affiliates`
- **Action**: `backend/app/Http/Actions/Affiliates/CreateAffiliateAction.php`
- **Handler**: `backend/app/Services/Application/Handlers/Affiliate/CreateAffiliateHandler.php`
- **Validation**: Name, code (3-20 chars, alphanumeric + hyphen/underscore), optional email

#### Update Affiliate
- **Endpoint**: `PUT /events/{eventId}/affiliates/{affiliateId}`
- **Action**: `backend/app/Http/Actions/Affiliates/UpdateAffiliateAction.php`
- **Handler**: `backend/app/Services/Application/Handlers/Affiliate/UpdateAffiliateHandler.php`
- **Note**: Code cannot be changed after creation

#### Delete Affiliate
- **Endpoint**: `DELETE /events/{eventId}/affiliates/{affiliateId}`
- **Action**: `backend/app/Http/Actions/Affiliates/DeleteAffiliateAction.php`
- **Handler**: `backend/app/Services/Application/Handlers/Affiliate/DeleteAffiliateHandler.php`

#### Get Affiliates
- **Endpoint**: `GET /events/{eventId}/affiliates`
- **Action**: `backend/app/Http/Actions/Affiliates/GetAffiliatesAction.php`
- **Handler**: `backend/app/Services/Application/Handlers/Affiliate/GetAffiliatesHandler.php`
- **Features**: Pagination, search by name/code/email

#### Get Single Affiliate
- **Endpoint**: `GET /events/{eventId}/affiliates/{affiliateId}`
- **Action**: `backend/app/Http/Actions/Affiliates/GetAffiliateAction.php`

### Route Registration
- **Location**: `backend/routes/api.php`
- **Resource Route**: `Route::resource('events.affiliates', ...)`

### Sales Tracking Integration

#### Order Creation
- **Public Action**: `backend/app/Http/Actions/Orders/Public/CreateOrderActionPublic.php`
- **Handler**: `backend/app/Services/Application/Handlers/Order/CreateOrderPublicHandler.php`
- Validates affiliate code and associates order with affiliate

#### Payment Success Tracking
Sales are incremented when orders are completed via:
1. **Stripe Handler**: `backend/app/Services/Domain/Payment/Stripe/EventHandlers/PaymentIntentSucceededHandler.php`
2. **Manual Payment**: `backend/app/Services/Domain/Order/MarkOrderAsPaidService.php`
3. **Order Completion**: `backend/app/Services/Application/Handlers/Order/CompleteOrderHandler.php`

### DTOs
- **Location**: `backend/app/Services/Application/Handlers/Affiliate/DTO/UpsertAffiliateDTO.php`
- **Fields**: name, code, email (optional), status (AffiliateStatus enum)

### Status Management
- **Enum**: `backend/app/DomainObjects/Status/AffiliateStatus.php`
- **Values**: `ACTIVE`, `INACTIVE` (uppercase only)
- **Business Rule**: Only ACTIVE affiliates can generate new sales
- **Uses BaseEnum**: Provides `valuesArray()` method for validation

### Validation Rules
- **Location**: `backend/app/Validators/Rules/AffiliateRules.php`
- **Create Rules**: name (required), code (required, regex), email (optional), status (uses AffiliateStatus::valuesArray())
- **Update Rules**: name, email, status (code cannot be updated)

## Frontend Implementation

### API Client
- **Location**: `frontend/src/api/affiliate.client.ts`
- **Types**: `Affiliate`, `CreateAffiliateRequest`, `UpdateAffiliateRequest` (all use uppercase status values)
- **Methods**: create, update, all, findById, delete

### React Query Hooks

#### Queries
- `frontend/src/queries/useGetAffiliates.ts` - List affiliates with pagination
- `frontend/src/queries/useGetAffiliate.ts` - Get single affiliate

#### Mutations
- `frontend/src/mutations/useCreateAffiliate.ts`
- `frontend/src/mutations/useUpdateAffiliate.ts`
- `frontend/src/mutations/useDeleteAffiliate.ts`

### UI Components

#### Main Page
- **Location**: `frontend/src/components/routes/event/Affiliates/index.tsx`
- **Features**: Search, pagination (only shows if >1 page), create button

#### Table Component
- **Location**: `frontend/src/components/common/AffiliateTable/index.tsx`
- **Styles**: `frontend/src/components/common/AffiliateTable/AffiliateTable.module.scss`
- **Design**: Minimal, dignified table layout with subtle styling
- **Columns**: 
  - Code: Monospace font with inline copy button, subtle gradient background
  - Affiliate: Name + email in clean hierarchy
  - Status: Light variant badges for active, outline for inactive
  - Performance: Compact stat boxes showing sales count and revenue
  - Actions: Contextual menu with edit, copy code, copy link, and delete
- **Features**: 
  - Interactive code copying with clipboard API
  - Compact performance metrics with icon indicators
  - Responsive action menu
  - Subtle hover effects and transitions
  - Monochromatic color scheme for elegance
  - Increased border radius for modern look

#### Legacy Card Component (Deprecated)
- **Location**: `frontend/src/components/common/AffiliateList/index.tsx`
- **Note**: Replaced by table component for better scalability with many affiliates

#### Shared Form Component
- **Location**: `frontend/src/components/forms/AffiliateForm/index.tsx`
- **Features**: Reusable form for both create and edit modals
- **Status Selection**: Uses CustomSelect component with icons and descriptions
- **Code Generation**: Random code generator for create mode
- **Validation**: Real-time form validation

#### Modals
- **Create**: `frontend/src/components/modals/CreateAffiliateModal/index.tsx`
- **Edit**: `frontend/src/components/modals/EditAffiliateModal/index.tsx`
- **Features**: Both use shared AffiliateForm component, form validation, status management
- **Create-specific**: Random code generation, editable code field
- **Edit-specific**: Code field disabled (cannot be changed)

### Event Dashboard Integration
- **Sidebar Link**: Added in `frontend/src/components/layouts/Event/index.tsx`
- **Route**: Added in `frontend/src/router.tsx` at `/manage/event/{eventId}/affiliates`
- **Icon**: Uses dollar sign icon to represent affiliate/commission tracking

### Public Order Integration
- **Location**: `frontend/src/components/routes/product-widget/SelectProducts/index.tsx`
- **Implementation**:
  - Reads `aff` parameter from URL on component mount
  - Stores affiliate code in localStorage with 30-day expiry
  - Includes affiliate_code in order payload (invisible to user)
  - SSR-safe implementation

## Affiliate Code Flow

1. **Affiliate shares link**: `https://event-url.com?aff=AFFILIATECODE`
2. **Customer visits link**: Code extracted from URL and stored in localStorage
3. **Code persistence**: Stored for 30 days, survives page refreshes
4. **Order creation**: Code automatically included in order payload
5. **Sales tracking**: Backend validates code and associates order with affiliate
6. **Commission tracking**: Sales incremented when payment succeeds

## Design Patterns

### Visual Design
- **Current**: Clean table-based layout for scalability with many affiliates
- **Legacy**: Card-based layout (deprecated) - was inspired by capacity assignments
- **Table Features**:
  - Responsive design with mobile/desktop action menus
  - Status badges (green for ACTIVE, gray for INACTIVE)
  - Interactive code copying with hover effects
  - Visual statistics with icons and proper hierarchy
  - Compact performance metrics in dedicated column
- **Form Design**: 
  - Custom status select with icons and descriptions
  - Subtle random code generation button
  - Proper field grouping and spacing

### Code Architecture
- **Repository pattern** with interfaces
- **Domain-driven design** with handlers and status enums
- **DTO pattern** for data transfer (uses AffiliateStatus enum)
- **Proper enum usage**: AffiliateStatus enum with BaseEnum trait for validation
- **Type safety**: TypeScript interfaces with uppercase status values
- **Form reusability**: Shared AffiliateForm component for create/edit
- **React Query** for data fetching and state management
- **SSR-compatible** implementation throughout

## Testing

### Backend Unit Tests
- **Location**: `backend/tests/Unit/Services/Application/Handlers/Affiliate/`
- **Test Files**:
  - `CreateAffiliateHandlerTest.php`
  - `UpdateAffiliateHandlerTest.php`
  - `DeleteAffiliateHandlerTest.php`
- **Coverage**: All handlers have comprehensive unit tests covering success paths and error conditions
- **Important**: Tests must extend Laravel's `Tests\TestCase`, not PHPUnit's `TestCase`
- **Mocking**: Uses Mockery for repository mocking
- **Run Tests**: `docker compose -f docker-compose.dev.yml exec backend php artisan test --filter=Affiliate`

### Testing Considerations
- Validate unique codes per event
- Test sales increment logic
- Verify authorization checks
- Test affiliate code validation in orders
- Test URL parameter extraction
- Verify localStorage persistence
- Test SSR compatibility
- Validate form inputs

## Future Enhancements

Potential improvements not yet implemented:
- Commission rate configuration
- Payout tracking and management
- Affiliate dashboard/portal
- Email notifications to affiliates
- Advanced reporting and analytics
- Tiered commission structures
- Affiliate link generation UI