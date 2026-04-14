# SES Bounce/Complaint Handling

## What This Does

Adds an SNS webhook endpoint that ingests SES bounce and complaint notifications, maintains an in-app suppression list, and checks that list before sending emails. Different behavior for transactional vs. marketing emails:

- **Permanent bounces** (invalid address): suppress ALL email types
- **Complaints** (user marked spam): suppress marketing only — transactional emails to paying customers still send
- **Transient bounces**: suppress marketing only — transactional may succeed on retry

## New Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_09_000000_create_email_suppressions_table.php` | Creates `email_suppressions` table |
| `app/Models/EmailSuppression.php` | Eloquent model |
| `app/DomainObjects/EmailSuppressionDomainObject.php` | Domain object |
| `app/DomainObjects/Generated/EmailSuppressionDomainObjectAbstract.php` | Generated abstract with field constants |
| `app/DomainObjects/Status/EmailSuppressionReasonEnum.php` | `BOUNCE`, `COMPLAINT` |
| `app/DomainObjects/Status/EmailSuppressionSourceEnum.php` | `SES_NOTIFICATION`, `MANUAL` |
| `app/Repository/Interfaces/EmailSuppressionRepositoryInterface.php` | Repository interface |
| `app/Repository/Eloquent/EmailSuppressionRepository.php` | Repository implementation |
| `app/Services/Domain/Email/EmailSuppressionService.php` | Suppression check/create/remove logic |
| `app/Http/Actions/Common/Webhooks/SesIncomingWebhookAction.php` | Webhook endpoint (mirrors Stripe pattern) |
| `app/Services/Application/Handlers/Email/Ses/DTO/SesWebhookDTO.php` | DTO for webhook payload |
| `app/Services/Application/Handlers/Email/Ses/IncomingSesWebhookHandler.php` | SNS message validation, routing, dedup |
| `app/Services/Domain/Email/Ses/EventHandlers/BounceHandler.php` | Creates suppression for bounces |
| `app/Services/Domain/Email/Ses/EventHandlers/ComplaintHandler.php` | Creates suppression for complaints |

## Modified Files

| File | Change |
|------|--------|
| `routes/api.php` | Added `POST /api/public/webhooks/ses` |
| `app/Providers/RepositoryServiceProvider.php` | Registered `EmailSuppressionRepository` |
| `app/Jobs/Event/SendEventEmailJob.php` | Checks suppression before sending, logs `SUPPRESSED` status |
| `app/Services/Domain/Mail/SendEventEmailMessagesService.php` | Pre-filters suppressed addresses before dispatching jobs |
| `app/DomainObjects/Status/OutgoingMessageStatus.php` | Added `SUPPRESSED` case |
| `config/services.php` | Added `sns_topic_arn`, `sns_verify_signature`, `suppression_enabled` under `ses` |
| `.env.example` | Added `AWS_SNS_TOPIC_ARN`, `AWS_SNS_VERIFY_SIGNATURE`, `SES_SUPPRESSION_ENABLED` |

## Configuration

Add to your `.env`:

```env
AWS_SNS_TOPIC_ARN=arn:aws:sns:us-east-1:123456789:ses-notifications
AWS_SNS_VERIFY_SIGNATURE=true
SES_SUPPRESSION_ENABLED=true
```

Set `SES_SUPPRESSION_ENABLED=false` (default) to disable all suppression checks without removing the code.

## Local Development Testing

### 1. Run the migration

```bash
cd backend
php artisan migrate
```

Verify the table exists:

```bash
php artisan tinker
>>> Schema::hasTable('email_suppressions')
# Should return true
```

### 2. Test the webhook endpoint accepts POST requests

```bash
# Should return 204 (even with garbage payload — errors are handled async)
curl -s -o /dev/null -w "%{http_code}" \
  -X POST http://localhost:1234/api/public/webhooks/ses \
  -H "Content-Type: application/json" \
  -d '{"Type": "Notification", "Message": "{}"}'
```

Expected: `204`

### 3. Test SNS SubscriptionConfirmation handling

```bash
curl -X POST http://localhost:1234/api/public/webhooks/ses \
  -H "Content-Type: application/json" \
  -d '{
    "Type": "SubscriptionConfirmation",
    "TopicArn": "arn:aws:sns:us-east-1:123456789:ses-notifications",
    "SubscribeURL": "https://httpbin.org/get"
  }'
```

Check logs — you should see "Confirming SNS subscription" and an HTTP GET to the SubscribeURL.

### 4. Test bounce notification creates a suppression record

Make sure `SES_SUPPRESSION_ENABLED=true` in your `.env`, then:

```bash
curl -X POST http://localhost:1234/api/public/webhooks/ses \
  -H "Content-Type: application/json" \
  -d '{
    "Type": "Notification",
    "MessageId": "test-bounce-001",
    "TopicArn": "'"$AWS_SNS_TOPIC_ARN"'",
    "Message": "{\"notificationType\":\"Bounce\",\"bounce\":{\"bounceType\":\"Permanent\",\"bounceSubType\":\"General\",\"bouncedRecipients\":[{\"emailAddress\":\"bounce-test@example.com\"}]}}"
  }'
```

If your queue is `sync`, the record is created immediately. If async, process the queue first:

```bash
php artisan queue:work --once
```

Verify the record:

```bash
php artisan tinker
>>> DB::table('email_suppressions')->where('email', 'bounce-test@example.com')->first()
```

You should see a row with `reason=bounce`, `bounce_type=Permanent`, `source=ses_notification`.

### 5. Test complaint notification

```bash
curl -X POST http://localhost:1234/api/public/webhooks/ses \
  -H "Content-Type: application/json" \
  -d '{
    "Type": "Notification",
    "MessageId": "test-complaint-001",
    "TopicArn": "'"$AWS_SNS_TOPIC_ARN"'",
    "Message": "{\"notificationType\":\"Complaint\",\"complaint\":{\"complaintFeedbackType\":\"abuse\",\"complainedRecipients\":[{\"emailAddress\":\"complaint-test@example.com\"}]}}"
  }'
```

### 6. Test suppression logic in tinker

```bash
php artisan tinker
```

```php
$svc = app(\HiEvents\Services\Domain\Email\EmailSuppressionService::class);

// Permanent bounce — suppressed for ALL types
$svc->isEmailSuppressed('bounce-test@example.com', null, 'marketing');      // true
$svc->isEmailSuppressed('bounce-test@example.com', null, 'transactional');  // true

// Complaint — suppressed for marketing only
$svc->isEmailSuppressed('complaint-test@example.com', null, 'marketing');      // true
$svc->isEmailSuppressed('complaint-test@example.com', null, 'transactional');  // false

// Clean address — never suppressed
$svc->isEmailSuppressed('clean@example.com', null, 'marketing');  // false
```

### 7. Test deduplication

Send the same bounce notification again (same `MessageId`):

```bash
curl -X POST http://localhost:1234/api/public/webhooks/ses \
  -H "Content-Type: application/json" \
  -d '{
    "Type": "Notification",
    "MessageId": "test-bounce-001",
    "TopicArn": "'"$AWS_SNS_TOPIC_ARN"'",
    "Message": "{\"notificationType\":\"Bounce\",\"bounce\":{\"bounceType\":\"Permanent\",\"bounceSubType\":\"General\",\"bouncedRecipients\":[{\"emailAddress\":\"bounce-test@example.com\"}]}}"
  }'
```

Check logs — should see "SNS message already handled". No duplicate rows in `email_suppressions`.

### 8. Test email sending with suppressed address

This requires an event with messages set up. The easiest path:

1. Create an event and add some attendees via the UI
2. Manually insert a suppression for one attendee's email:
   ```php
   // In tinker
   $svc = app(\HiEvents\Services\Domain\Email\EmailSuppressionService::class);
   $svc->suppressEmail(
       email: 'attendee@example.com',
       reason: 'bounce',
       source: 'manual',
       bounceType: 'Permanent',
   );
   ```
3. Send a message to all attendees via the UI
4. Check `outgoing_messages` table — the suppressed attendee should have status `SUPPRESSED`

### 9. Test removing a suppression (un-suppress)

```php
// In tinker
$svc = app(\HiEvents\Services\Domain\Email\EmailSuppressionService::class);
$svc->removeSuppression('bounce-test@example.com');

// Verify soft-deleted
DB::table('email_suppressions')
    ->where('email', 'bounce-test@example.com')
    ->whereNotNull('deleted_at')
    ->exists();  // true

// No longer suppressed
$svc->isEmailSuppressed('bounce-test@example.com', null, 'marketing');  // false
```

## Production Setup with Real SES

1. In the SES console, set up a Configuration Set with an SNS destination for Bounce and Complaint events
2. Create an SNS topic and subscribe the webhook URL: `https://your-domain.com/api/public/webhooks/ses`
3. SNS will send a `SubscriptionConfirmation` — the handler auto-confirms it
4. Set `AWS_SNS_TOPIC_ARN` to your topic ARN
5. Set `SES_SUPPRESSION_ENABLED=true`
6. Test with SES simulator addresses:
   - `bounce@simulator.amazonses.com` — triggers a bounce notification
   - `complaint@simulator.amazonses.com` — triggers a complaint notification
