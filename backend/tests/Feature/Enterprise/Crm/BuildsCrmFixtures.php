<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use HiEvents\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Concerns\ManagesLicence;

trait BuildsCrmFixtures
{
    use ActsAsAccountUsers;
    use ManagesFeatureFlags;
    use ManagesLicence;

    protected User $owner;

    protected int $accountId;

    protected int $organizerId;

    protected int $eventId;

    protected int $occurrenceId;

    protected int $productId;

    protected int $priceId;

    private array $checkInListIds = [];

    protected function setUpCrmOrganizer(): void
    {
        $this->useCloudLicence();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, true);

        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->trackOrganizer($this->accountId, $this->organizerId);

        $this->eventId = $this->createEvent($this->accountId, $this->organizerId, 'Main Event');
        $this->occurrenceId = $this->insertOccurrence($this->eventId, now()->addDays(10));
        [$this->productId, $this->priceId] = $this->insertTicket($this->eventId);
    }

    protected function trackOrganizer(int $accountId, int $organizerId): void
    {
        DB::table('crm_organizer_states')->insert([
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function insertOccurrence(int $eventId, CarbonInterface $startsAt): int
    {
        return DB::table('event_occurrences')->insertGetId([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => $startsAt->toDateTimeString(),
            'end_date' => $startsAt->copy()->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function insertTicket(int $eventId): array
    {
        $productId = DB::table('products')->insertGetId([
            'title' => 'General Admission',
            'event_id' => $eventId,
            'product_type' => 'TICKET',
            'type' => 'PAID',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $priceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$productId, $priceId];
    }

    protected function insertOrder(array $attributes = []): int
    {
        return DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'public_id' => 'PUB_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => OrderStatus::COMPLETED->name,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'total_gross' => 50,
            'total_refunded' => 0,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
            ...$attributes,
        ]);
    }

    protected function insertAttendee(int $orderId, array $attributes = []): int
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        $ownsDefaultEvent = (int) $order->event_id === $this->eventId;

        return DB::table('attendees')->insertGetId([
            'short_id' => 'att_'.uniqid(),
            'public_id' => 'ATT_'.uniqid(),
            'order_id' => $orderId,
            'event_id' => $order->event_id,
            'event_occurrence_id' => $ownsDefaultEvent ? $this->occurrenceId : null,
            'product_id' => $this->productId,
            'product_price_id' => $this->priceId,
            'status' => AttendeeStatus::ACTIVE->name,
            'first_name' => $order->first_name,
            'last_name' => $order->last_name,
            'email' => $order->email,
            'created_at' => $order->created_at,
            'updated_at' => $order->created_at,
            ...$attributes,
        ]);
    }

    protected function insertCheckIn(int $attendeeId, array $attributes = []): int
    {
        $attendee = DB::table('attendees')->where('id', $attendeeId)->first();

        $this->checkInListIds[$attendee->event_id] ??= DB::table('check_in_lists')->insertGetId([
            'short_id' => 'cil_'.uniqid(),
            'name' => 'Door',
            'event_id' => $attendee->event_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('attendee_check_ins')->insertGetId([
            'short_id' => 'ci_'.uniqid(),
            'check_in_list_id' => $this->checkInListIds[$attendee->event_id],
            'product_id' => $attendee->product_id,
            'attendee_id' => $attendeeId,
            'order_id' => $attendee->order_id,
            'event_id' => $attendee->event_id,
            'event_occurrence_id' => $attendee->event_occurrence_id,
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
            ...$attributes,
        ]);
    }

    protected function insertWaitlistEntry(array $attributes = []): int
    {
        return DB::table('waitlist_entries')->insertGetId([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'product_price_id' => $this->priceId,
            'email' => 'ada@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'status' => 'WAITING',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
            ...$attributes,
        ]);
    }

    protected function insertContact(string $email, array $attributes = [], ?int $organizerId = null, ?int $accountId = null): int
    {
        $organizerId ??= $this->organizerId;

        $contactId = DB::table('crm_contacts')->insertGetId([
            'account_id' => $accountId ?? $this->accountId,
            'organizer_id' => $organizerId,
            'email' => $email,
            'source' => 'MANUAL',
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);

        DB::table('crm_contact_emails')->insert([
            'organizer_id' => $organizerId,
            'contact_id' => $contactId,
            'email' => $email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $contactId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function insertContactRows(array $rows): void
    {
        DB::table('crm_contacts')->insert($rows);
        DB::statement('
            INSERT INTO crm_contact_emails (organizer_id, contact_id, email, created_at, updated_at)
            SELECT c.organizer_id, c.id, c.email, c.created_at, c.updated_at
            FROM crm_contacts c
            WHERE NOT EXISTS (SELECT 1 FROM crm_contact_emails e WHERE e.contact_id = c.id)
        ');
    }

    protected function insertTag(string $name, ?int $organizerId = null, ?int $accountId = null): int
    {
        return DB::table('crm_tags')->insertGetId([
            'account_id' => $accountId ?? $this->accountId,
            'organizer_id' => $organizerId ?? $this->organizerId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function insertSubscribedContact(string $email, array $attributes = [], ?int $organizerId = null): int
    {
        $organizerId ??= $this->organizerId;
        $contactId = $this->insertContact($email, ['email_marketing_status' => 'SUBSCRIBED', ...$attributes], $organizerId);
        $this->insertConsent($contactId, $email, 'OPT_IN', now()->subDays(30), $organizerId);

        return $contactId;
    }

    protected function insertConsent(int $contactId, string $email, string $action, CarbonInterface $occurredAt, ?int $organizerId = null): int
    {
        return DB::table('crm_consent_events')->insertGetId([
            'organizer_id' => $organizerId ?? $this->organizerId,
            'contact_id' => $contactId,
            'email' => $email,
            'action' => $action,
            'source_type' => 'MANUAL',
            'occurred_at' => $occurredAt,
        ]);
    }

    protected function insertCampaign(array $attributes = [], ?int $organizerId = null, ?int $accountId = null): int
    {
        return DB::table('crm_campaigns')->insertGetId([
            'account_id' => $accountId ?? $this->accountId,
            'organizer_id' => $organizerId ?? $this->organizerId,
            'name' => 'Spring news',
            'subject' => 'Hello from us',
            'body' => '<p>See you soon</p>',
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    protected function insertRecipient(int $campaignId, ?int $contactId, string $email, array $attributes = [], ?int $organizerId = null): int
    {
        return DB::table('crm_campaign_recipients')->insertGetId([
            'campaign_id' => $campaignId,
            'organizer_id' => $organizerId ?? $this->organizerId,
            'contact_id' => $contactId,
            'email' => $email,
            'status' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    protected function insertDelivery(string $providerMessageId, array $attributes = []): int
    {
        return DB::table('email_deliveries')->insertGetId([
            'provider_message_id' => $providerMessageId,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    protected function campaign(int $campaignId): ?object
    {
        return DB::table('crm_campaigns')->where('id', $campaignId)->first();
    }

    protected function verifyAccount(?int $accountId = null, array $attributes = []): void
    {
        DB::table('accounts')->where('id', $accountId ?? $this->accountId)->update([
            'account_verified_at' => now()->subYear(),
            'is_manually_verified' => true,
            ...$attributes,
        ]);
    }

    protected function messagingTierId(string $name): int
    {
        return (int) DB::table('account_messaging_tiers')->where('name', $name)->value('id');
    }

    protected function projectOrders(int ...$orderIds): void
    {
        app(ContactSyncService::class)->projectOrders($this->accountId, $this->organizerId, $orderIds);
    }

    protected function projectWaitlistEntries(int ...$entryIds): void
    {
        app(ContactSyncService::class)->projectWaitlistEntries($this->accountId, $this->organizerId, $entryIds);
    }

    protected function contact(string $email, ?int $organizerId = null): ?object
    {
        return DB::table('crm_contacts')
            ->where('organizer_id', $organizerId ?? $this->organizerId)
            ->where('email', $email)
            ->first();
    }

    protected function contactId(string $email): int
    {
        $contact = $this->contact($email);
        $this->assertNotNull($contact, "No contact was projected for $email");

        return (int) $contact->id;
    }

    /**
     * @return Collection<int, object>
     */
    protected function activities(string $email, ?string $type = null): Collection
    {
        return DB::table('crm_contact_activities')
            ->where('contact_id', $this->contactId($email))
            ->when($type !== null, static fn ($query) => $query->where('type', $type))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return string[]
     */
    protected function activityTypes(string $email): array
    {
        return $this->activities($email)->pluck('type')->sort()->values()->all();
    }

    /**
     * @return string[]
     */
    protected function contactEmails(): array
    {
        return DB::table('crm_contacts')
            ->where('organizer_id', $this->organizerId)
            ->orderBy('email')
            ->pluck('email')
            ->all();
    }
}
