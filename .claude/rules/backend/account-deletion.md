---
paths:
  - "backend/app/**/DeletionRequest*/**"
  - "backend/app/**/*Deletion*.php"
  - "backend/app/Services/Domain/Account/Anonymization/**"
  - "frontend/src/**/*eletion*"
---

# Account deletion

- Hard delete can't rely on cascades (many foreign keys to events, orders and organizers are NO ACTION). A new account-owned table needs an anonymizer in `Services/Domain/Account/Anonymization/` and a place in the `AccountHardDeletionService` delete order.
- `account_deletion_requests` has no foreign keys on purpose, so rows survive the hard delete.
- Never delete `organizer_configurations` or `account_configuration` rows (shared plan rows) or `ticket_lookup_tokens` (buyer-email scoped across accounts). `stripe_customers` has no account foreign key; match it by Stripe account id.
- Users can belong to several accounts: a sole-account user is scrubbed and soft-deleted, and their `user_identities` rows are deleted so they can't sign back in with single sign-on; a shared user only loses the `account_users` row.
- An account's SSO connection, its domains and the `user_identities` tagged with the connection are deleted before `accounts` (`SsoConnectionAnonymizer` mirrors this).
