# Hi.Events Backend Documentation

Welcome to the Hi.Events backend documentation. This directory contains comprehensive guides on the architecture, patterns, and best practices used throughout the backend codebase.

**🚨 These docs are AI generated and are not 100% accurate. Always verify anything important by looking at the actual code.**

## Documentation Index

### [Architecture Overview](architecture-overview.md)
**Start here** - High-level overview of the entire backend architecture.

**Contents**:
- Architectural layers (HTTP, Application, Domain, Infrastructure)
- Core components (Domain Objects, DTOs, Repositories, Events)
- Request flow and data flow
- Key design patterns
- Multi-tenancy architecture
- Best practices summary

**Who should read**: Everyone working on the backend

---

### [Domain-Driven Design](domain-driven-design.md)
Deep dive into the DDD patterns used in Hi.Events.

**Contents**:
- Application Layer (Handlers)
- Domain Services Layer
- Data Transfer Objects (DTOs)
- Domain Objects (auto-generated)
- Enums and constants
- DTO flow patterns
- Transaction management
- Service composition
- Validation patterns

**Who should read**: Backend developers implementing new features

---

### [Database Schema](database-schema.md)
Complete database schema architecture and entity relationships.

**Contents**:
- Core entity hierarchy
- Multi-tenancy architecture
- All database entities (Account, Event, Order, Attendee, etc.)
- Entity relationships and diagrams
- Architectural patterns (soft deletes, JSONB, indexes)
- PostgreSQL-specific features

**Who should read**: Backend developers, database administrators

---

### [Repository Pattern](repository-pattern.md)
Guide to the repository pattern implementation.

**Contents**:
- Base repository interface (40+ methods)
- Creating new repositories
- Usage in handlers
- Best practices (favor base methods, eager loading)
- Common patterns (pagination, filters, bulk operations)
- Testing with mocks

**Who should read**: Backend developers working with data access

---

### [Events and Background Jobs](events-and-jobs.md)
Event-driven architecture and asynchronous processing.

**Contents**:
- Application Events vs Infrastructure Events
- Event listeners
- Background jobs
- Event flow examples
- Retry strategies
- Transaction boundaries
- Queue separation

**Who should read**: Backend developers implementing workflows and integrations

---

### [API Patterns and HTTP Layer](api-patterns.md)
HTTP layer patterns and API design.

**Contents**:
- BaseAction pattern
- Response methods
- Authorization patterns
- JSON API resources
- Routing patterns
- Request validation
- Exception handling

**Who should read**: Backend developers building API endpoints

---

## Quick Reference

### Common Tasks

#### Creating a New Feature

1. **Read**: [Architecture Overview](architecture-overview.md) - Understand the layers
2. **Read**: [Domain-Driven Design](domain-driven-design.md) - Understand DTOs, Handlers, Services
3. **Reference**: Existing feature in `/prompts` directory
4. **Implement**: Following the established patterns

#### Adding a New Entity

1. **Create migration**: `php artisan make:migration create_xxx_table`
2. **Run migration**: `php artisan migrate`
3. **Generate domain objects**: `php artisan generate-domain-objects`
4. **Create repository interface and implementation**: See [Repository Pattern](repository-pattern.md)
5. **Register repository**: Add to `RepositoryServiceProvider`
6. **Update database docs**: Reference [Database Schema](database-schema.md)

#### Adding a New API Endpoint

1. **Create FormRequest**: See [API Patterns](api-patterns.md#request-validation)
2. **Create Action**: Extend `BaseAction`, see [API Patterns](api-patterns.md#baseaction-pattern)
3. **Create DTO**: Extend `BaseDataObject`, see [DDD](domain-driven-design.md#dtos)
4. **Create Handler**: See [DDD](domain-driven-design.md#application-layer)
5. **Create Domain Service** (if needed): See [DDD](domain-driven-design.md#domain-services-layer)
6. **Create JSON Resource**: See [API Patterns](api-patterns.md#json-api-resources)
7. **Add route**: `routes/api.php`

#### Adding Background Processing

1. **Create Event**: See [Events and Jobs](events-and-jobs.md#application-events)
2. **Create Job**: See [Events and Jobs](events-and-jobs.md#background-jobs)
3. **Create Listener**: See [Events and Jobs](events-and-jobs.md#event-listeners)
4. **Register** (if needed): See [Events and Jobs](events-and-jobs.md#event-registration)

### Key Commands

```bash
# Backend (run in Docker container)
cd docker/development
docker compose -f docker-compose.dev.yml exec backend bash

# Generate domain objects
php artisan generate-domain-objects

# Run migrations
php artisan migrate

# Run unit tests
php artisan test --testsuite=Unit

# Run specific test
php artisan test --filter=TestName
```

### File Locations

```
backend/
├── app/
│   ├── DomainObjects/          # Auto-generated domain objects
│   │   ├── Generated/          # Don't edit these
│   │   ├── Enums/             # General enums
│   │   └── Status/            # Status enums
│   ├── Events/                # Application events
│   ├── Http/
│   │   ├── Actions/           # HTTP actions (controllers)
│   │   ├── Request/           # Form requests
│   │   └── Resources/         # JSON API resources
│   ├── Jobs/                  # Background jobs
│   ├── Listeners/             # Event listeners
│   ├── Models/                # Eloquent models
│   ├── Repository/
│   │   ├── Interfaces/        # Repository contracts
│   │   └── Eloquent/          # Implementations
│   └── Services/
│       ├── Application/       # Application handlers
│       │   └── Handlers/      # Use case handlers
│       ├── Domain/            # Domain services
│       └── Infrastructure/    # External services
├── database/
│   └── migrations/            # Database migrations
└── routes/
    └── api.php                # API routes
```

## Architecture Principles

### Core Principles

1. **Domain-Driven Design**: Clear separation between domain, application, and infrastructure
2. **Repository Pattern**: All data access through interfaces
3. **DTO Pattern**: Immutable data transfer between layers
4. **Event-Driven**: Decoupled communication via events
5. **Type Safety**: Strong typing with domain objects and DTOs

### Best Practices

1. **Always extend `BaseDataObject`** for new DTOs (not `BaseDTO`)
2. **Use domain object constants** for field names
3. **Favor base repository methods** over custom methods
4. **Extend `BaseAction`** for all HTTP actions
5. **Use enums** for domain constants

### Code Quality

- Follow PSR-12 coding standards
- Wrap all translatable strings in `__()` helper
- Create unit tests for new features
- Don't add comments unless absolutely necessary
- Refactor complex code instead of documenting it

## External Resources

- [CLAUDE.md](../../CLAUDE.md) - Project guidelines for AI assistants
- [Laravel Documentation](https://laravel.com/docs)
- [Spatie Laravel Data](https://spatie.be/docs/laravel-data)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)

## Contributing

When adding new features or making significant changes:

1. Follow the patterns documented here
2. Update relevant documentation if patterns change
3. Add examples to `/prompts` for reference
4. Ensure tests pass: `php artisan test --testsuite=Unit`

## Getting Help

1. **Start with**: [Architecture Overview](architecture-overview.md)
2. **Look at examples**: `/prompts` directory contains feature documentation
3. **Check CLAUDE.md**: Project-specific guidelines and patterns
4. **Reference specific guides**: Use the documentation index above

---

**Last Updated**: 2025-10-29

**Documentation Version**: 1.0
