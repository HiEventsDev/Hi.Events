<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\Enterprise\Crm\Jobs\ProjectOrdersJob;
use HiEvents\Enterprise\Crm\Jobs\ProjectWaitlistEntriesJob;
use HiEvents\Enterprise\Crm\Jobs\ReconcileContactsJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\CreateContactHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\MergeContactsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetEventAudienceHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactService;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ContactProjectionTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();
    }

    public function test_an_order_email_becomes_one_contact_keyed_on_the_trimmed_lowercased_email(): void
    {
        $first = $this->insertOrder(['email' => '  Ada@Example.COM ']);
        $second = $this->insertOrder(['email' => 'ada@example.com']);

        $this->projectOrders($first, $second);

        $this->assertSame(['ada@example.com'], $this->contactEmails());
        $contact = $this->contact('ada@example.com');
        $this->assertSame('ORDER', $contact->source);
        $this->assertSame($this->accountId, (int) $contact->account_id);
        $this->assertSame('Ada', $contact->first_name);
        $this->assertSame('Lovelace', $contact->last_name);
        $this->assertSame(2, (int) $contact->orders_count);
        $this->assertSame(
            ['ada@example.com'],
            DB::table('crm_contact_emails')->where('contact_id', $contact->id)->pluck('email')->all(),
        );
    }

    public function test_an_attendee_with_a_different_email_gets_their_own_contact_holding_the_ticket_as_self(): void
    {
        $orderId = $this->insertOrder();
        $attendeeId = $this->insertAttendee($orderId, ['email' => 'Grace@Example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper']);

        $this->projectOrders($orderId);

        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->contactEmails());
        $this->assertSame(['ORDER_COMPLETED'], $this->activityTypes('ada@example.com'));

        $ticket = $this->activities('grace@example.com')->sole();
        $this->assertSame('TICKET_HELD', $ticket->type);
        $this->assertSame('SELF', $ticket->role);
        $this->assertSame('ATTENDEE', $ticket->source_type);
        $this->assertSame($attendeeId, (int) $ticket->source_id);
        $this->assertSame($orderId, (int) $ticket->order_id);
        $this->assertSame($this->eventId, (int) $ticket->event_id);
        $this->assertSame($this->occurrenceId, (int) $ticket->event_occurrence_id);
        $this->assertSame($this->productId, (int) $ticket->product_id);

        $grace = $this->contact('grace@example.com');
        $this->assertSame('ATTENDEE', $grace->source);
        $this->assertSame('Grace', $grace->first_name);
        $this->assertSame(0, (int) $grace->orders_count);
        $this->assertSame(1, (int) $grace->tickets_count);
    }

    public static function attendeesOnTheBuyersContact(): array
    {
        return [
            'same email and name is the buyer' => ['ada@example.com', 'Ada', 'Lovelace', 'SELF'],
            'same email in another case and name in another case is the buyer' => ['ADA@example.com', 'ada', 'LOVELACE', 'SELF'],
            'no email and the same name is the buyer' => [null, 'Ada', 'Lovelace', 'SELF'],
            'same email and a different name is a guest' => ['ada@example.com', 'Charles', 'Babbage', 'GUEST'],
            'no email and a different name is a guest' => [null, 'Charles', 'Babbage', 'GUEST'],
            'blank email and a different name is a guest' => ['  ', 'Charles', 'Babbage', 'GUEST'],
        ];
    }

    #[DataProvider('attendeesOnTheBuyersContact')]
    public function test_an_attendee_without_their_own_email_is_held_on_the_buyers_contact(?string $email, string $firstName, string $lastName, string $role): void
    {
        $orderId = $this->insertOrder();
        $this->insertAttendee($orderId, ['email' => $email, 'first_name' => $firstName, 'last_name' => $lastName]);

        $this->projectOrders($orderId);

        $this->assertSame(['ada@example.com'], $this->contactEmails());
        $ticket = $this->activities('ada@example.com', 'TICKET_HELD')->sole();
        $this->assertSame($role, $ticket->role);
        $this->assertSame($firstName, $ticket->first_name);
    }

    public function test_a_guest_name_never_becomes_the_contacts_display_name(): void
    {
        $orderId = $this->insertOrder(['first_name' => null, 'last_name' => null]);
        $this->insertAttendee($orderId, ['email' => null, 'first_name' => 'Charles', 'last_name' => 'Babbage']);

        $this->projectOrders($orderId);

        $contact = $this->contact('ada@example.com');
        $this->assertNull($contact->first_name);
        $this->assertNull($contact->last_name);
    }

    public function test_nothing_is_projected_when_neither_the_order_nor_the_attendee_has_an_email(): void
    {
        $orderId = $this->insertOrder(['email' => null]);
        $this->insertAttendee($orderId, ['email' => null]);

        $this->projectOrders($orderId);

        $this->assertSame([], $this->contactEmails());
        $this->assertSame(0, DB::table('crm_contact_activities')->where('organizer_id', $this->organizerId)->count());
    }

    public static function invalidEmails(): array
    {
        return [
            'no at sign' => ['not-an-email'],
            'inner whitespace' => ['ada lovelace@example.com'],
            'two at signs' => ['ada@home@example.com'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidEmails')]
    public function test_an_invalid_order_email_is_skipped_while_a_valid_attendee_on_the_order_still_projects(string $email): void
    {
        $orderId = $this->insertOrder(['email' => $email]);
        $this->insertAttendee($orderId, ['email' => 'grace@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper']);

        $this->projectOrders($orderId);

        $this->assertSame(['grace@example.com'], $this->contactEmails());
        $this->assertSame(['TICKET_HELD'], $this->activityTypes('grace@example.com'));
    }

    public function test_orders_of_another_organizer_are_never_projected_into_this_one(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $otherEventId = $this->createEvent($this->accountId, $otherOrganizerId, 'Other Event');
        $foreignOrder = $this->insertOrder(['event_id' => $otherEventId, 'email' => 'foreign@example.com']);
        $this->insertAttendee($foreignOrder, ['email' => 'foreign-guest@example.com']);

        $this->projectOrders($foreignOrder);

        $this->assertSame([], $this->contactEmails());
        $this->assertSame(0, DB::table('crm_contacts')->where('organizer_id', $otherOrganizerId)->count());
    }

    public function test_the_same_email_is_a_separate_contact_per_organizer(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $otherEventId = $this->createEvent($this->accountId, $otherOrganizerId, 'Other Event');
        $mine = $this->insertOrder();
        $theirs = $this->insertOrder(['event_id' => $otherEventId]);

        $this->projectOrders($mine);
        $this->trackOrganizer($this->accountId, $otherOrganizerId);
        app(ContactSyncService::class)->projectOrders($this->accountId, $otherOrganizerId, [$theirs]);

        $this->assertSame(1, (int) $this->contact('ada@example.com')->orders_count);
        $this->assertSame(1, (int) $this->contact('ada@example.com', $otherOrganizerId)->orders_count);
        $this->assertNotSame($this->contact('ada@example.com')->id, $this->contact('ada@example.com', $otherOrganizerId)->id);
    }

    public static function orderStatusActivities(): array
    {
        return [
            'completed' => [['status' => 'COMPLETED'], ['ORDER_COMPLETED']],
            'awaiting offline payment' => [['status' => 'AWAITING_OFFLINE_PAYMENT'], ['ORDER_AWAITING_PAYMENT']],
            'cancelled' => [['status' => 'CANCELLED'], ['ORDER_CANCELLED']],
            'abandoned' => [['status' => 'ABANDONED'], ['CHECKOUT_ABANDONED']],
            'completed and partly refunded' => [['status' => 'COMPLETED', 'total_refunded' => 20], ['ORDER_COMPLETED', 'ORDER_REFUNDED']],
            'cancelled with a refund' => [['status' => 'CANCELLED', 'total_refunded' => 50], ['ORDER_CANCELLED']],
        ];
    }

    #[DataProvider('orderStatusActivities')]
    public function test_an_order_status_maps_to_its_activities(array $order, array $expectedTypes): void
    {
        $orderId = $this->insertOrder($order);

        $this->projectOrders($orderId);

        $this->assertSame($expectedTypes, $this->activityTypes('ada@example.com'));
        $this->assertSame([$orderId], $this->activities('ada@example.com')->pluck('order_id')->unique()->map(static fn ($id) => (int) $id)->all());
    }

    public function test_an_expired_reservation_is_an_abandoned_checkout_and_a_live_one_is_not_projected(): void
    {
        $expired = $this->insertOrder(['status' => 'RESERVED', 'email' => 'expired@example.com', 'reserved_until' => now()->subMinute()]);
        $live = $this->insertOrder(['status' => 'RESERVED', 'email' => 'live@example.com', 'reserved_until' => now()->addMinutes(10)]);

        $this->projectOrders($expired, $live);

        $this->assertSame(['expired@example.com'], $this->contactEmails());
        $this->assertSame(['CHECKOUT_ABANDONED'], $this->activityTypes('expired@example.com'));
    }

    public function test_order_activities_carry_the_amount_currency_and_promo_code(): void
    {
        $promoCodeId = DB::table('promo_codes')->insertGetId([
            'code' => 'early',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $this->insertOrder([
            'currency' => 'EUR',
            'total_gross' => 80,
            'total_refunded' => 30,
            'promo_code_id' => $promoCodeId,
            'promo_code' => 'early',
        ]);

        $this->projectOrders($orderId);

        $completed = $this->activities('ada@example.com', 'ORDER_COMPLETED')->sole();
        $refunded = $this->activities('ada@example.com', 'ORDER_REFUNDED')->sole();
        $this->assertEqualsWithDelta(80.0, (float) $completed->amount, 0.001);
        $this->assertSame('EUR', $completed->currency);
        $this->assertSame($promoCodeId, (int) $completed->promo_code_id);
        $this->assertSame('early', $completed->label);
        $this->assertEqualsWithDelta(30.0, (float) $refunded->amount, 0.001);
    }

    public function test_only_active_attendees_hold_tickets_and_only_live_check_ins_count(): void
    {
        $orderId = $this->insertOrder();
        $active = $this->insertAttendee($orderId);
        $cancelled = $this->insertAttendee($orderId, ['status' => 'CANCELLED']);
        $deleted = $this->insertAttendee($orderId, ['deleted_at' => now()]);
        $checkInId = $this->insertCheckIn($active);
        $this->insertCheckIn($active, ['deleted_at' => now()]);
        $this->insertCheckIn($deleted);

        $this->projectOrders($orderId);

        $this->assertSame([$active], $this->activities('ada@example.com', 'TICKET_HELD')->pluck('source_id')->map(static fn ($id) => (int) $id)->all());
        $checkIn = $this->activities('ada@example.com', 'CHECKED_IN')->sole();
        $this->assertSame($checkInId, (int) $checkIn->source_id);
        $this->assertSame('CHECK_IN', $checkIn->source_type);
        $this->assertSame('SELF', $checkIn->role);
        $this->assertSame($this->occurrenceId, (int) $checkIn->event_occurrence_id);
        $this->assertNotContains($cancelled, $this->activities('ada@example.com')->pluck('source_id')->all());
    }

    public function test_an_attendee_scanned_on_several_lists_counts_one_check_in_and_a_cancelled_one_counts_none(): void
    {
        $orderId = $this->insertOrder();
        $active = $this->insertAttendee($orderId);
        $cancelled = $this->insertAttendee($orderId, ['status' => 'CANCELLED']);
        $firstScanId = $this->insertCheckIn($active, ['created_at' => now()->subHours(3)]);
        $secondListId = DB::table('check_in_lists')->insertGetId([
            'short_id' => 'cil_'.uniqid(),
            'name' => 'VIP door',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertCheckIn($active, ['check_in_list_id' => $secondListId, 'created_at' => now()->subHours(2)]);
        $this->insertCheckIn($cancelled);

        $this->projectOrders($orderId);
        $this->projectOrders($orderId);

        $checkIn = $this->activities('ada@example.com', 'CHECKED_IN')->sole();
        $this->assertSame($firstScanId, (int) $checkIn->source_id);
        $this->assertSame(1, (int) $this->contact('ada@example.com')->attended_count);
        $audience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId);
        $this->assertSame([1, 1], [$audience->tickets_count, $audience->checked_in_count]);
    }

    public function test_an_offline_order_only_counts_its_opt_in_once_it_is_marked_paid(): void
    {
        $orderId = $this->insertOrder(['status' => 'AWAITING_OFFLINE_PAYMENT', 'opted_into_marketing_at' => now()->subDay()]);
        $this->projectOrders($orderId);
        $this->assertSame('NOT_SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);

        DB::table('orders')->where('id', $orderId)->update(['status' => 'COMPLETED']);
        $this->projectOrders($orderId);
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);

        DB::table('orders')->where('id', $orderId)->update(['status' => 'CANCELLED']);
        $this->projectOrders($orderId);
        $this->assertSame('NOT_SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_emails_padded_with_tabs_or_line_breaks_resolve_to_one_contact(): void
    {
        $orderId = $this->insertOrder(['email' => "\tAda@Example.com\r\n"]);
        $this->insertAttendee($orderId, ['email' => " ada@example.com\t"]);
        $this->projectWaitlistEntries($this->insertWaitlistEntry(['email' => "\nADA@example.com "]));
        $this->projectOrders($orderId);

        $this->assertSame(['ada@example.com'], $this->contactEmails());
        $this->assertSame(['ORDER_COMPLETED', 'TICKET_HELD', 'WAITLIST_JOINED'], $this->activityTypes('ada@example.com'));
    }

    public function test_a_deleted_contact_stays_deleted_when_the_app_key_is_rotated(): void
    {
        $orderId = $this->insertOrder();
        $this->projectOrders($orderId);
        app(DeletedContactService::class)->delete($this->organizerId, [$this->contactId('ada@example.com')]);

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->projectOrders($orderId);

        $this->assertNull($this->contact('ada@example.com'));
        $salt = DB::table('crm_deleted_contact_salts')->where('organizer_id', $this->organizerId)->value('salt');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $salt);
        $this->assertSame(
            DeletedContactHasher::hashWithSalt($salt, 'ada@example.com'),
            DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->value('email_hash'),
        );
    }

    public function test_every_organizer_gets_its_own_tombstone_salt_without_being_tracked(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $untrackedOrganizerId = $this->createOrganizer($this->accountId, 'Untracked Organizer');
        $this->trackOrganizer($this->accountId, $otherOrganizerId);
        $hasher = app(DeletedContactHasher::class);

        $this->assertNotSame($hasher->salt($this->organizerId), $hasher->salt($otherOrganizerId));
        $this->assertSame($hasher->salt($untrackedOrganizerId), $hasher->salt($untrackedOrganizerId));
        $this->assertSame(0, DB::table('crm_organizer_states')->where('organizer_id', $untrackedOrganizerId)->count());
    }

    public function test_adding_and_deleting_a_contact_on_an_unsynced_organizer_does_not_start_tracking_it(): void
    {
        $organizerId = $this->createOrganizer($this->accountId, 'Unsynced Organizer');
        $eventId = $this->createEvent($this->accountId, $organizerId, 'Unsynced Event');
        $contact = app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $organizerId,
            first_name: null,
            last_name: null,
            email: 'manual@example.com',
        ));
        app(DeletedContactService::class)->delete($organizerId, [$contact->getId()]);
        $orderId = $this->insertOrder(['event_id' => $eventId, 'email' => 'buyer@example.com']);

        app(ContactSyncService::class)->projectOrders($this->accountId, $organizerId, [$orderId]);

        $this->assertSame(0, DB::table('crm_organizer_states')->where('organizer_id', $organizerId)->count());
        $this->assertSame(0, DB::table('crm_contacts')->where('organizer_id', $organizerId)->count());
        $this->assertSame(1, DB::table('crm_deleted_contacts')->where('organizer_id', $organizerId)->count());
    }

    public function test_a_waitlist_entry_becomes_a_contact_with_a_waitlist_activity(): void
    {
        $entryId = $this->insertWaitlistEntry(['email' => ' Wanda@Example.com', 'first_name' => 'Wanda', 'last_name' => 'Wait']);
        $deletedEntryId = $this->insertWaitlistEntry(['email' => 'gone@example.com', 'deleted_at' => now()]);

        $this->projectWaitlistEntries($entryId, $deletedEntryId);

        $this->assertSame(['wanda@example.com'], $this->contactEmails());
        $contact = $this->contact('wanda@example.com');
        $this->assertSame('WAITLIST', $contact->source);
        $this->assertSame('Wanda', $contact->first_name);
        $this->assertSame(0, (int) $contact->events_count);

        $activity = $this->activities('wanda@example.com')->sole();
        $this->assertSame('WAITLIST_JOINED', $activity->type);
        $this->assertSame('WAITLIST_ENTRY', $activity->source_type);
        $this->assertSame($entryId, (int) $activity->source_id);
        $this->assertSame($this->occurrenceId, (int) $activity->event_occurrence_id);
        $this->assertNull($activity->order_id);
    }

    public function test_a_removed_waitlist_entry_takes_its_activity_and_its_empty_contact_with_it(): void
    {
        $entryId = $this->insertWaitlistEntry(['email' => 'wanda@example.com']);
        $this->projectWaitlistEntries($entryId);

        DB::table('waitlist_entries')->where('id', $entryId)->update(['deleted_at' => now()]);
        $this->projectWaitlistEntries($entryId);

        $this->assertSame([], $this->contactEmails());
    }

    public function test_rollups_count_orders_tickets_events_attendance_and_dates(): void
    {
        $secondEventId = $this->createEvent($this->accountId, $this->organizerId, 'Second Event');
        $secondOccurrenceId = $this->insertOccurrence($secondEventId, now()->subDays(20));

        $first = $this->insertOrder(['created_at' => '2026-01-10 10:00:00', 'updated_at' => '2026-01-10 10:00:00']);
        $firstTicket = $this->insertAttendee($first);
        $guestTicket = $this->insertAttendee($first, ['email' => null, 'first_name' => 'Charles', 'last_name' => 'Babbage']);
        $second = $this->insertOrder(['event_id' => $secondEventId, 'created_at' => '2026-03-05 09:00:00', 'updated_at' => '2026-03-05 09:00:00']);
        $secondTicket = $this->insertAttendee($second, ['event_occurrence_id' => $secondOccurrenceId]);
        $cancelled = $this->insertOrder(['status' => 'CANCELLED', 'created_at' => '2026-04-01 09:00:00', 'updated_at' => '2026-04-02 09:00:00']);
        $this->insertCheckIn($firstTicket, ['created_at' => '2026-02-01 19:00:00']);
        $this->insertCheckIn($guestTicket, ['created_at' => '2026-02-01 19:05:00']);
        $this->insertCheckIn($secondTicket, ['created_at' => '2026-03-20 18:30:00']);

        $this->projectOrders($first, $second, $cancelled);

        $contact = $this->contact('ada@example.com');
        $this->assertSame(2, (int) $contact->orders_count);
        $this->assertSame(3, (int) $contact->tickets_count);
        $this->assertSame(2, (int) $contact->events_count);
        $this->assertSame(2, (int) $contact->attended_count);
        $this->assertSame('2026-01-10 10:00:00', $contact->first_seen_at);
        $this->assertSame('2026-03-05 09:00:00', $contact->last_order_at);
        $this->assertSame('2026-03-20 18:30:00', $contact->last_attended_at);
        $this->assertSame('2026-04-02 09:00:00', $contact->last_activity_at);
    }

    public function test_a_guest_checking_in_does_not_count_as_the_buyer_attending(): void
    {
        $order = $this->insertOrder();
        $this->insertAttendee($order);
        $guestTicket = $this->insertAttendee($order, ['email' => null, 'first_name' => 'Charles', 'last_name' => 'Babbage']);
        $this->insertCheckIn($guestTicket);

        $this->projectOrders($order);

        $contact = $this->contact('ada@example.com');
        $this->assertSame([2, 0, null], [(int) $contact->tickets_count, (int) $contact->attended_count, $contact->last_attended_at]);
        $this->assertSame(1, app(CrmContactRepositoryInterface::class)->countNoShows((int) $contact->id, now()->addYears(5)));
    }

    public function test_spend_is_kept_per_currency_with_refunds_tracked_separately(): void
    {
        $this->projectOrders(
            $this->insertOrder(['currency' => 'USD', 'total_gross' => 50]),
            $this->insertOrder(['currency' => 'USD', 'total_gross' => 30, 'total_refunded' => 10]),
            $this->insertOrder(['currency' => 'EUR', 'total_gross' => 99.5]),
            $this->insertOrder(['currency' => 'USD', 'total_gross' => 500, 'status' => 'CANCELLED']),
            $this->insertOrder(['currency' => 'USD', 'total_gross' => 700, 'status' => 'AWAITING_OFFLINE_PAYMENT']),
            $this->insertOrder(['currency' => 'GBP', 'total_gross' => 900, 'status' => 'ABANDONED']),
        );

        $spend = DB::table('crm_contact_spend')
            ->where('contact_id', $this->contactId('ada@example.com'))
            ->orderBy('currency')
            ->get()
            ->map(static fn (object $row) => [$row->currency, (float) $row->total_gross, (float) $row->total_refunded])
            ->all();

        $this->assertSame([['EUR', 99.5, 0.0], ['USD', 80.0, 10.0]], $spend);
    }

    public function test_projecting_twice_changes_nothing(): void
    {
        $orderId = $this->insertOrder(['opted_into_marketing_at' => now()->subDays(5), 'total_refunded' => 5]);
        $attendeeId = $this->insertAttendee($orderId);
        $this->insertAttendee($orderId, ['email' => 'grace@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper']);
        $this->insertCheckIn($attendeeId);
        $entryId = $this->insertWaitlistEntry();

        $this->projectOrders($orderId);
        $this->projectWaitlistEntries($entryId);
        $before = $this->projectionSnapshot();

        $this->projectOrders($orderId);
        $this->projectWaitlistEntries($entryId);
        $this->projectOrders($orderId, $orderId);

        $this->assertSame($before, $this->projectionSnapshot());
        $this->assertCount(2, $before['contacts']);
        $this->assertCount(6, $before['activities']);
        $this->assertCount(1, $before['consent']);
    }

    public function test_changing_an_attendee_email_moves_the_ticket_and_removes_the_emptied_contact(): void
    {
        $orderId = $this->insertOrder();
        $attendeeId = $this->insertAttendee($orderId, ['email' => 'grace@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper']);
        $this->insertCheckIn($attendeeId);
        $this->projectOrders($orderId);
        $this->assertSame(1, (int) $this->contact('grace@example.com')->attended_count);

        DB::table('attendees')->where('id', $attendeeId)->update(['email' => 'hopper@example.com']);
        $this->projectOrders($orderId);

        $this->assertSame(['ada@example.com', 'hopper@example.com'], $this->contactEmails());
        $this->assertSame(['CHECKED_IN', 'TICKET_HELD'], $this->activityTypes('hopper@example.com'));
        $this->assertSame(1, (int) $this->contact('hopper@example.com')->tickets_count);
        $this->assertSame(1, (int) $this->contact('hopper@example.com')->attended_count);
        $this->assertSame(0, DB::table('crm_contact_emails')->where('email', 'grace@example.com')->count());
    }

    public function test_changing_an_order_email_moves_its_activities_and_recomputes_both_contacts(): void
    {
        $kept = $this->insertOrder(['total_gross' => 40]);
        $moved = $this->insertOrder(['total_gross' => 60]);
        $this->insertAttendee($moved);
        $this->projectOrders($kept, $moved);
        $this->assertSame(2, (int) $this->contact('ada@example.com')->orders_count);

        DB::table('orders')->where('id', $moved)->update(['email' => 'countess@example.com']);
        DB::table('attendees')->where('order_id', $moved)->update(['email' => 'countess@example.com']);
        $this->projectOrders($moved);

        $ada = $this->contact('ada@example.com');
        $countess = $this->contact('countess@example.com');
        $this->assertSame([1, 0], [(int) $ada->orders_count, (int) $ada->tickets_count]);
        $this->assertSame([1, 1], [(int) $countess->orders_count, (int) $countess->tickets_count]);
        $this->assertEqualsWithDelta(40.0, (float) DB::table('crm_contact_spend')->where('contact_id', $ada->id)->value('total_gross'), 0.001);
        $this->assertEqualsWithDelta(60.0, (float) DB::table('crm_contact_spend')->where('contact_id', $countess->id)->value('total_gross'), 0.001);
    }

    public function test_cancelling_an_order_and_its_tickets_rewrites_the_activities_and_rollups(): void
    {
        $orderId = $this->insertOrder();
        $attendeeId = $this->insertAttendee($orderId);
        $this->projectOrders($orderId);
        $contactId = $this->contactId('ada@example.com');

        DB::table('orders')->where('id', $orderId)->update(['status' => 'CANCELLED', 'updated_at' => now()]);
        DB::table('attendees')->where('id', $attendeeId)->update(['status' => 'CANCELLED']);
        $this->projectOrders($orderId);

        $contact = $this->contact('ada@example.com');
        $this->assertSame($contactId, (int) $contact->id);
        $this->assertSame(['ORDER_CANCELLED'], $this->activityTypes('ada@example.com'));
        $this->assertSame([0, 0, 0], [(int) $contact->orders_count, (int) $contact->tickets_count, (int) $contact->events_count]);
        $this->assertNull($contact->last_order_at);
        $this->assertSame(0, DB::table('crm_contact_spend')->where('contact_id', $contactId)->count());
    }

    public function test_a_later_edit_to_a_cancelled_or_refunded_order_keeps_the_activity_date(): void
    {
        $this->freezeSecond();
        $cancelledId = $this->insertOrder(['status' => 'CANCELLED', 'updated_at' => now()->subDays(3)]);
        $refundedId = $this->insertOrder(['email' => 'grace@example.com', 'total_gross' => 50, 'total_refunded' => 20, 'updated_at' => now()]);
        DB::table('order_refunds')->insert([
            'order_id' => $refundedId,
            'payment_provider' => 'STRIPE',
            'refund_id' => 're_'.uniqid(),
            'amount' => 20,
            'currency' => 'USD',
            'status' => 'succeeded',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $this->projectOrders($cancelledId, $refundedId);

        DB::table('orders')->whereIn('id', [$cancelledId, $refundedId])->update(['notes' => 'Edited', 'updated_at' => now()]);
        $this->projectOrders($cancelledId, $refundedId);

        $occurredAt = static fn (string $type) => DB::table('crm_contact_activities')->where('type', $type)->value('occurred_at');
        $this->assertSame(now()->subDays(3)->toDateTimeString(), $occurredAt('ORDER_CANCELLED'));
        $this->assertSame(now()->subDays(2)->toDateTimeString(), $occurredAt('ORDER_REFUNDED'));
    }

    public function test_a_refund_after_projection_is_reflected_in_spend(): void
    {
        $orderId = $this->insertOrder(['total_gross' => 50]);
        $this->projectOrders($orderId);

        DB::table('orders')->where('id', $orderId)->update(['total_refunded' => 50, 'updated_at' => now()]);
        $this->projectOrders($orderId);

        $spend = DB::table('crm_contact_spend')->where('contact_id', $this->contactId('ada@example.com'))->sole();
        $this->assertEqualsWithDelta(50.0, (float) $spend->total_gross, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $spend->total_refunded, 0.001);
    }

    public static function removals(): array
    {
        return [
            'order soft deleted' => ['orders'],
            'event soft deleted' => ['events'],
        ];
    }

    #[DataProvider('removals')]
    public function test_soft_deleting_the_source_removes_the_activities_and_the_now_empty_contact(string $table): void
    {
        $orderId = $this->insertOrder();
        $this->insertCheckIn($this->insertAttendee($orderId));
        $this->projectOrders($orderId);
        $this->assertCount(3, $this->activities('ada@example.com'));

        DB::table($table)->where('id', $table === 'orders' ? $orderId : $this->eventId)->update(['deleted_at' => now()]);
        $this->projectOrders($orderId);

        $this->assertSame([], $this->contactEmails());
        $this->assertSame(0, DB::table('crm_contact_activities')->where('organizer_id', $this->organizerId)->count());
        $this->assertSame(1, DB::table('orders')->where('id', $orderId)->count());
    }

    public function test_an_undone_check_in_lowers_the_attended_count(): void
    {
        $orderId = $this->insertOrder();
        $checkInId = $this->insertCheckIn($this->insertAttendee($orderId));
        $this->projectOrders($orderId);
        $this->assertSame(1, (int) $this->contact('ada@example.com')->attended_count);

        DB::table('attendee_check_ins')->where('id', $checkInId)->update(['deleted_at' => now()]);
        $this->projectOrders($orderId);

        $contact = $this->contact('ada@example.com');
        $this->assertSame(0, (int) $contact->attended_count);
        $this->assertNull($contact->last_attended_at);
    }

    public static function contactsWorthKeeping(): array
    {
        return [
            'tagged' => ['tag'],
            'has a note' => ['note'],
            'name edited by hand' => ['manual_name'],
            'unsubscribed by hand' => ['manual_consent'],
        ];
    }

    #[DataProvider('contactsWorthKeeping')]
    public function test_an_emptied_contact_survives_when_the_organizer_has_added_something_to_it(string $addition): void
    {
        $orderId = $this->insertOrder();
        $this->projectOrders($orderId);
        $contactId = $this->contactId('ada@example.com');

        match ($addition) {
            'tag' => DB::table('crm_contact_tags')->insert(['contact_id' => $contactId, 'tag_id' => $this->insertTag('VIP')]),
            'note' => DB::table('crm_contact_notes')->insert(['contact_id' => $contactId, 'organizer_id' => $this->organizerId, 'note' => 'Met at the door']),
            'manual_name' => DB::table('crm_contacts')->where('id', $contactId)->update(['name_is_manual' => true]),
            'manual_consent' => app(CrmContactRepositoryInterface::class)->insertConsentEvent(
                $this->organizerId, $contactId, 'ada@example.com', CrmConsentAction::OPT_OUT, CrmConsentSource::MANUAL, null, null,
            ),
        };

        DB::table('orders')->where('id', $orderId)->update(['deleted_at' => now()]);
        $this->projectOrders($orderId);

        $contact = $this->contact('ada@example.com');
        $this->assertNotNull($contact);
        $this->assertSame(0, (int) $contact->orders_count);
    }

    public function test_a_manually_created_contact_is_never_removed_by_the_projection(): void
    {
        $contactId = $this->insertContact('ada@example.com');
        $orderId = $this->insertOrder();
        $this->projectOrders($orderId);
        $this->assertSame(1, (int) $this->contact('ada@example.com')->orders_count);

        DB::table('orders')->where('id', $orderId)->update(['deleted_at' => now()]);
        $this->projectOrders($orderId);

        $this->assertSame($contactId, $this->contactId('ada@example.com'));
        $this->assertSame('MANUAL', $this->contact('ada@example.com')->source);
    }

    public function test_the_display_name_follows_the_latest_buyer_name(): void
    {
        $older = $this->insertOrder(['first_name' => 'Ada', 'last_name' => 'Byron', 'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(30)]);
        $this->projectOrders($older);
        $this->assertSame('Byron', $this->contact('ada@example.com')->last_name);

        $newer = $this->insertOrder(['first_name' => 'Augusta', 'last_name' => 'King', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        $this->insertAttendee($newer, ['first_name' => 'Ticket', 'last_name' => 'Holder', 'created_at' => now()]);
        $this->projectOrders($newer);

        $contact = $this->contact('ada@example.com');
        $this->assertSame(['Augusta', 'King'], [$contact->first_name, $contact->last_name]);

        $this->projectOrders($older);
        $contact = $this->contact('ada@example.com');
        $this->assertSame(['Augusta', 'King'], [$contact->first_name, $contact->last_name]);
    }

    public function test_a_manual_name_is_not_overwritten_by_later_orders(): void
    {
        $this->projectOrders($this->insertOrder());
        DB::table('crm_contacts')
            ->where('id', $this->contactId('ada@example.com'))
            ->update(['first_name' => 'Countess', 'last_name' => 'Lovelace', 'name_is_manual' => true]);

        $this->projectOrders($this->insertOrder(['first_name' => 'A.', 'last_name' => 'L.', 'created_at' => now(), 'updated_at' => now()]));

        $contact = $this->contact('ada@example.com');
        $this->assertSame(['Countess', 'Lovelace'], [$contact->first_name, $contact->last_name]);
        $this->assertSame(2, (int) $contact->orders_count);
    }

    public static function consentingStatuses(): array
    {
        return [
            'completed' => ['COMPLETED', 'SUBSCRIBED'],
            'awaiting offline payment' => ['AWAITING_OFFLINE_PAYMENT', 'NOT_SUBSCRIBED'],
            'abandoned' => ['ABANDONED', 'NOT_SUBSCRIBED'],
            'cancelled' => ['CANCELLED', 'NOT_SUBSCRIBED'],
        ];
    }

    #[DataProvider('consentingStatuses')]
    public function test_a_marketing_opt_in_only_counts_on_a_completed_order(string $status, string $expected): void
    {
        $optedInAt = now()->subDays(4)->startOfSecond();
        $orderId = $this->insertOrder(['status' => $status, 'opted_into_marketing_at' => $optedInAt]);

        $this->projectOrders($orderId);

        $contact = $this->contact('ada@example.com');
        $this->assertSame($expected, $contact->email_marketing_status);

        $events = DB::table('crm_consent_events')->where('contact_id', $contact->id)->get();
        if ($expected === 'NOT_SUBSCRIBED') {
            $this->assertCount(0, $events);

            return;
        }

        $event = $events->sole();
        $this->assertSame(['OPT_IN', 'ORDER', $orderId, 'ada@example.com'], [$event->action, $event->source_type, (int) $event->source_id, $event->email]);
        $this->assertSame($optedInAt->toDateTimeString(), $event->occurred_at);
        $this->assertSame($optedInAt->toDateTimeString(), $contact->email_marketing_status_changed_at);
    }

    public function test_an_order_without_an_opt_in_leaves_the_contact_not_subscribed(): void
    {
        $this->projectOrders($this->insertOrder());

        $this->assertSame('NOT_SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_reprojecting_an_old_opted_in_order_does_not_resubscribe_someone_who_unsubscribed_since(): void
    {
        $orderId = $this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]);
        $this->projectOrders($orderId);
        $contactId = $this->contactId('ada@example.com');

        $this->recordConsent($contactId, CrmConsentAction::OPT_OUT);
        app(CrmProjectionRepositoryInterface::class)->refreshContacts($this->organizerId, [$contactId]);
        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);

        $this->projectOrders($orderId);
        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => null, 'created_at' => now(), 'updated_at' => now()]));
        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subDays(2)]));

        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_opting_in_on_a_new_order_after_unsubscribing_subscribes_again(): void
    {
        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]));
        $contactId = $this->contactId('ada@example.com');
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $contactId,
            'email' => 'ada@example.com',
            'action' => 'OPT_OUT',
            'source_type' => 'MANUAL',
            'occurred_at' => now()->subDays(3),
        ]);

        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subDay()]));

        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_a_spam_complaint_is_sticky_even_when_a_later_order_opts_in(): void
    {
        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]));
        $contactId = $this->contactId('ada@example.com');
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $contactId,
            'email' => 'ada@example.com',
            'action' => 'COMPLAINED',
            'source_type' => 'CAMPAIGN',
            'occurred_at' => now()->subDays(3),
        ]);

        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subHour()]));

        $this->assertSame('COMPLAINED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_a_hard_bounce_is_sticky_even_when_a_later_order_opts_in(): void
    {
        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]));
        $contactId = $this->contactId('ada@example.com');
        DB::table('crm_consent_events')->insert([
            'organizer_id' => $this->organizerId,
            'contact_id' => $contactId,
            'email' => 'ada@example.com',
            'action' => 'BOUNCED',
            'source_type' => 'CAMPAIGN',
            'occurred_at' => now()->subDays(3),
        ]);

        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subHour()]));

        $this->assertSame('BOUNCED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_campaign_and_import_activities_do_not_move_first_seen_or_last_activity(): void
    {
        $placedAt = now()->subDays(5)->startOfSecond();
        $orderId = $this->insertOrder(['created_at' => $placedAt, 'updated_at' => $placedAt]);
        $this->projectOrders($orderId);
        $before = $this->contact('ada@example.com');
        $contactId = (int) $before->id;
        foreach ([['CAMPAIGN_SENT', 'CAMPAIGN_RECIPIENT'], ['IMPORTED', 'IMPORT']] as [$type, $sourceType]) {
            foreach ([now()->subYears(2), now()->addMinute()] as $index => $occurredAt) {
                DB::table('crm_contact_activities')->insert([
                    'organizer_id' => $this->organizerId,
                    'contact_id' => $contactId,
                    'type' => $type,
                    'source_type' => $sourceType,
                    'source_id' => 900000 + $index,
                    'email' => 'ada@example.com',
                    'occurred_at' => $occurredAt,
                ]);
            }
        }

        $this->projectOrders($orderId);

        $after = $this->contact('ada@example.com');
        $this->assertSame(4, DB::table('crm_contact_activities')->where('contact_id', $contactId)->whereIn('type', ['CAMPAIGN_SENT', 'IMPORTED'])->count());
        $this->assertSame([$before->first_seen_at, $before->last_activity_at], [$after->first_seen_at, $after->last_activity_at]);
        $this->assertSame($placedAt->toDateTimeString(), $after->last_activity_at);
    }

    public function test_moving_an_opted_in_order_to_another_email_stops_treating_the_old_address_as_subscribed(): void
    {
        $kept = $this->insertOrder();
        $moved = $this->insertOrder(['opted_into_marketing_at' => now()->subDays(4)]);
        $this->projectOrders($kept, $moved);
        $this->assertSame('SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);

        DB::table('orders')->where('id', $moved)->update(['email' => 'countess@example.com']);
        $this->projectOrders($moved);

        $this->assertSame('SUBSCRIBED', $this->contact('countess@example.com')->email_marketing_status);
        $this->assertSame('NOT_SUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_deleting_a_contact_removes_only_crm_rows_and_blocks_the_email_from_being_projected_again(): void
    {
        $orderId = $this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]);
        $attendeeId = $this->insertAttendee($orderId);
        $guestAttendeeId = $this->insertAttendee($orderId, ['email' => 'grace@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper']);
        $this->insertCheckIn($attendeeId);
        $this->projectOrders($orderId);
        $contactId = $this->contactId('ada@example.com');
        DB::table('crm_contact_tags')->insert(['contact_id' => $contactId, 'tag_id' => $this->insertTag('VIP')]);
        DB::table('crm_contact_notes')->insert(['contact_id' => $contactId, 'organizer_id' => $this->organizerId, 'note' => 'A note']);

        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);

        foreach (['crm_contact_emails', 'crm_contact_activities', 'crm_contact_spend', 'crm_contact_tags', 'crm_contact_notes', 'crm_consent_events'] as $table) {
            $this->assertSame(0, DB::table($table)->where('contact_id', $contactId)->count(), $table);
        }
        $this->assertSame(['grace@example.com'], $this->contactEmails());
        $this->assertNull(DB::table('orders')->where('id', $orderId)->value('deleted_at'));
        $this->assertSame('ada@example.com', DB::table('orders')->where('id', $orderId)->value('email'));
        $this->assertSame(2, DB::table('attendees')->whereIn('id', [$attendeeId, $guestAttendeeId])->whereNull('deleted_at')->count());
        $this->assertSame(1, DB::table('attendee_check_ins')->where('attendee_id', $attendeeId)->whereNull('deleted_at')->count());
        $this->assertSame(1, DB::table('crm_tags')->where('organizer_id', $this->organizerId)->count());

        $this->projectOrders($orderId, $this->insertOrder(['email' => 'ADA@example.com']));
        $this->projectWaitlistEntries($this->insertWaitlistEntry());

        $this->assertSame(['grace@example.com'], $this->contactEmails());
        $this->assertSame(['TICKET_HELD'], $this->activityTypes('grace@example.com'));
    }

    public function test_the_deletion_record_stores_a_salted_hash_and_not_the_email(): void
    {
        $this->projectOrders($this->insertOrder());

        app(DeletedContactService::class)->delete($this->organizerId, [$this->contactId('ada@example.com')]);

        $row = DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->sole();
        $this->assertSame(app(DeletedContactHasher::class)->hash($this->organizerId, 'ada@example.com'), $row->email_hash);
        $this->assertNotSame(hash('sha256', 'ada@example.com'), $row->email_hash);
    }

    public function test_a_deletion_only_suppresses_the_email_for_that_organizer(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $otherEventId = $this->createEvent($this->accountId, $otherOrganizerId, 'Other Event');
        $this->projectOrders($this->insertOrder());
        app(DeletedContactService::class)->delete($this->organizerId, [$this->contactId('ada@example.com')]);

        $this->trackOrganizer($this->accountId, $otherOrganizerId);

        app(ContactSyncService::class)->projectOrders($this->accountId, $otherOrganizerId, [$this->insertOrder(['event_id' => $otherEventId])]);

        $this->assertNull($this->contact('ada@example.com'));
        $this->assertNotNull($this->contact('ada@example.com', $otherOrganizerId));
    }

    public function test_deleting_a_merged_contact_suppresses_every_one_of_its_emails(): void
    {
        $first = $this->insertOrder();
        $second = $this->insertOrder(['email' => 'countess@example.com']);
        $this->projectOrders($first, $second);
        app(MergeContactsHandler::class)->handle($this->organizerId, $this->contactId('ada@example.com'), $this->contactId('countess@example.com'));

        app(DeletedContactService::class)->delete($this->organizerId, [$this->contactId('ada@example.com')]);
        $this->projectOrders($first, $second);

        $this->assertSame([], $this->contactEmails());
        $this->assertSame(2, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_restoring_a_deleted_contact_brings_back_their_order_history(): void
    {
        $orderId = $this->insertOrder();
        $this->projectOrders($orderId);
        app(DeletedContactService::class)->delete($this->organizerId, [$this->contactId('ada@example.com')]);

        $created = app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: ' Ada@Example.com ',
            restore_deleted: true,
        ));

        $this->assertSame(0, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->count());
        $contact = $this->contact('ada@example.com');
        $this->assertSame($created->getId(), (int) $contact->id);
        $this->assertSame('MANUAL', $contact->source);
        $this->assertSame(1, (int) $contact->orders_count);
        $this->assertSame('Ada', $contact->first_name);
    }

    public function test_restoring_a_deleted_contact_finds_their_orders_and_tickets_however_the_address_was_typed_and_nobody_elses(): void
    {
        $ownOrderId = $this->insertOrder(['email' => " Ada_Lovelace@Example.com\t"]);
        $giftOrderId = $this->insertOrder(['email' => 'friend@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper']);
        $this->insertAttendee($giftOrderId, ['email' => 'ADA_LOVELACE@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $lookalikeOrderId = $this->insertOrder(['email' => 'adaXlovelace@example.com']);
        $this->projectOrders($ownOrderId, $giftOrderId, $lookalikeOrderId);
        app(DeletedContactService::class)->delete($this->organizerId, [$this->contactId('ada_lovelace@example.com')]);

        $restored = app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: 'ada_lovelace@example.com',
            restore_deleted: true,
        ));

        $this->assertSame([1, 1], [$restored->getOrdersCount(), $restored->getTicketsCount()]);
        $this->assertSame(
            [$ownOrderId, $giftOrderId],
            DB::table('crm_contact_activities')->where('contact_id', $restored->getId())->orderBy('order_id')->pluck('order_id')->all(),
        );
    }

    public static function statusesAtDeletion(): array
    {
        return [
            'unsubscribed' => ['OPT_OUT', 'UNSUBSCRIBED', 'UNSUBSCRIBED', ['OPT_IN', 'OPT_OUT'], 'UNSUBSCRIBED'],
            'bounced' => ['BOUNCED', 'BOUNCED', 'BOUNCED', ['BOUNCED', 'OPT_IN'], 'BOUNCED'],
            'complained' => ['COMPLAINED', 'COMPLAINED', 'COMPLAINED', ['COMPLAINED', 'OPT_IN'], 'COMPLAINED'],
            'subscribed' => [null, 'SUBSCRIBED', null, ['OPT_IN'], 'SUBSCRIBED'],
        ];
    }

    #[DataProvider('statusesAtDeletion')]
    public function test_recreating_a_deleted_contact_restores_their_order_consent_but_keeps_any_suppression(
        ?string $consentAction,
        string $statusAtDeletion,
        ?string $suppression,
        array $actionsAfterRecreation,
        string $statusAfterRecreation,
    ): void {
        $orderId = $this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]);
        $this->projectOrders($orderId);
        $contactId = $this->contactId('ada@example.com');
        if ($consentAction !== null) {
            app(ConsentRecorder::class)->record(
                app(CrmContactRepositoryInterface::class)->findById($contactId),
                CrmConsentAction::from($consentAction),
                CrmConsentSource::MANUAL,
            );
        }
        $this->assertSame($statusAtDeletion, $this->contact('ada@example.com')->email_marketing_status);

        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);

        $this->assertSame($suppression, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->sole()->suppression);

        $created = app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: 'ada@example.com',
            restore_deleted: true,
        ));

        $this->assertSame($statusAfterRecreation, $created->getEmailMarketingStatus());
        $this->assertSame(
            $actionsAfterRecreation,
            DB::table('crm_consent_events')->where('contact_id', $created->getId())->orderBy('action')->pluck('action')->all(),
        );
        $this->assertSame($statusAfterRecreation, $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_a_contact_deleted_while_bounced_and_unsubscribed_comes_back_unsubscribed_so_clearing_a_bounce_can_never_make_them_mailable(): void
    {
        $this->projectOrders($this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]));
        $contactId = $this->contactId('ada@example.com');
        $contacts = app(CrmContactRepositoryInterface::class);
        app(ConsentRecorder::class)->record($contacts->findById($contactId), CrmConsentAction::OPT_OUT, CrmConsentSource::MANUAL);
        app(ConsentRecorder::class)->record($contacts->findById($contactId), CrmConsentAction::BOUNCED, CrmConsentSource::MANUAL);
        $this->assertSame('BOUNCED', $this->contact('ada@example.com')->email_marketing_status);

        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);
        $restored = app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: 'ada@example.com',
            restore_deleted: true,
        ));

        $this->assertSame('UNSUBSCRIBED', $restored->getEmailMarketingStatus());
        $this->assertSame(
            ['OPT_IN', 'OPT_OUT'],
            DB::table('crm_consent_events')->where('contact_id', $restored->getId())->orderBy('action')->pluck('action')->all(),
        );
    }

    public function test_a_recreated_suppressed_contact_is_not_resubscribed_by_their_old_opted_in_order(): void
    {
        $orderId = $this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]);
        $this->projectOrders($orderId);
        $contactId = $this->contactId('ada@example.com');
        app(ConsentRecorder::class)->record(app(CrmContactRepositoryInterface::class)->findById($contactId), CrmConsentAction::OPT_OUT, CrmConsentSource::MANUAL);
        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);
        app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: 'ada@example.com',
            restore_deleted: true,
        ));

        $this->projectOrders($orderId);

        $this->assertSame('UNSUBSCRIBED', $this->contact('ada@example.com')->email_marketing_status);
    }

    public function test_every_email_of_a_deleted_contact_carries_the_contacts_opt_out_even_the_address_that_bounced(): void
    {
        $first = $this->insertOrder();
        $second = $this->insertOrder(['email' => 'countess@example.com']);
        $third = $this->insertOrder(['email' => 'byron@example.com']);
        $this->projectOrders($first, $second, $third);
        $contactId = $this->contactId('ada@example.com');
        $contacts = app(CrmContactRepositoryInterface::class);
        app(ConsentRecorder::class)->record($contacts->findById($this->contactId('countess@example.com')), CrmConsentAction::BOUNCED, CrmConsentSource::MANUAL);
        app(MergeContactsHandler::class)->handle($this->organizerId, $contactId, $this->contactId('countess@example.com'));
        app(MergeContactsHandler::class)->handle($this->organizerId, $contactId, $this->contactId('byron@example.com'));
        app(ConsentRecorder::class)->record($contacts->findById($contactId), CrmConsentAction::OPT_OUT, CrmConsentSource::MANUAL);

        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);

        $hasher = app(DeletedContactHasher::class);
        $this->assertEquals(
            [
                $hasher->hash($this->organizerId, 'ada@example.com') => 'UNSUBSCRIBED',
                $hasher->hash($this->organizerId, 'countess@example.com') => 'UNSUBSCRIBED',
                $hasher->hash($this->organizerId, 'byron@example.com') => 'UNSUBSCRIBED',
            ],
            DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->pluck('suppression', 'email_hash')->all(),
        );
    }

    public static function suppressionsMadeOnAnotherAddress(): array
    {
        return [
            'unsubscribed on the primary address' => ['ada@example.com', 'OPT_OUT', 'UNSUBSCRIBED'],
            'unsubscribed on the other address' => ['countess@example.com', 'OPT_OUT', 'UNSUBSCRIBED'],
            'complained on the primary address' => ['ada@example.com', 'COMPLAINED', 'COMPLAINED'],
            'complained on the other address' => ['countess@example.com', 'COMPLAINED', 'COMPLAINED'],
        ];
    }

    #[DataProvider('suppressionsMadeOnAnotherAddress')]
    public function test_an_opt_out_or_complaint_on_one_address_of_a_deleted_contact_still_holds_whichever_address_is_added_again(
        string $suppressedAddress,
        string $action,
        string $statusOnceRestored,
    ): void {
        $first = $this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]);
        $second = $this->insertOrder(['email' => 'countess@example.com', 'opted_into_marketing_at' => now()->subDays(5)]);
        $this->projectOrders($first, $second);
        $contactId = $this->contactId('ada@example.com');
        app(MergeContactsHandler::class)->handle($this->organizerId, $contactId, $this->contactId('countess@example.com'));
        app(ConsentRecorder::class)->recordForEmail($this->organizerId, $contactId, $suppressedAddress, CrmConsentAction::from($action), CrmConsentSource::MANUAL);
        $this->assertSame($statusOnceRestored, $this->contact('ada@example.com')->email_marketing_status);

        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);

        foreach (['ada@example.com', 'countess@example.com'] as $email) {
            $restored = app(CreateContactHandler::class)->handle(new UpsertContactDTO(
                account_id: $this->accountId,
                organizer_id: $this->organizerId,
                first_name: null,
                last_name: null,
                email: $email,
                restore_deleted: true,
            ));

            $this->assertSame($statusOnceRestored, $restored->getEmailMarketingStatus(), $email);
        }
    }

    public static function consentHistories(): array
    {
        return [
            'no consent events' => [[], null],
            'opted in' => [['OPT_IN'], null],
            'opted out' => [['OPT_OUT'], 'UNSUBSCRIBED'],
            'opted in then out' => [['OPT_IN', 'OPT_OUT'], 'UNSUBSCRIBED'],
            'opted out then in again' => [['OPT_OUT', 'OPT_IN'], null],
            'bounced then opted in' => [['BOUNCED', 'OPT_IN'], 'BOUNCED'],
            'opted out then bounced' => [['OPT_OUT', 'BOUNCED'], 'UNSUBSCRIBED'],
            'bounced then opted out' => [['BOUNCED', 'OPT_OUT'], 'UNSUBSCRIBED'],
            'opted out, bounced, then opted in again' => [['OPT_OUT', 'BOUNCED', 'OPT_IN'], 'BOUNCED'],
            'complained then bounced then opted in' => [['COMPLAINED', 'BOUNCED', 'OPT_IN'], 'COMPLAINED'],
            'bounced then complained' => [['BOUNCED', 'COMPLAINED'], 'COMPLAINED'],
        ];
    }

    #[DataProvider('consentHistories')]
    public function test_the_suppression_of_a_deleted_email_is_a_complaint_then_an_opt_out_then_a_bounce(array $actions, ?string $suppression): void
    {
        $contactId = $this->insertContact('ada@example.com');
        $otherContactId = $this->insertContact('grace@example.com');
        $this->insertConsent($otherContactId, 'ada@example.com', 'COMPLAINED', now()->subDays(50));
        foreach ($actions as $index => $action) {
            $this->insertConsent($contactId, 'ada@example.com', $action, now()->subDays(40 - $index));
        }

        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);

        $this->assertSame($suppression, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->sole()->suppression);
    }

    public static function secondaryEmailSuppressions(): array
    {
        return [
            'bounced' => ['BOUNCED', 'SUBSCRIBED', 'BOUNCED'],
            'complained' => ['COMPLAINED', 'COMPLAINED', 'COMPLAINED'],
            'opted out' => ['OPT_OUT', 'UNSUBSCRIBED', 'UNSUBSCRIBED'],
        ];
    }

    #[DataProvider('secondaryEmailSuppressions')]
    public function test_a_contact_recreated_from_a_suppressed_secondary_email_is_not_resubscribed_by_an_order_opt_in(string $action, string $statusWhileSecondary, string $status): void
    {
        $first = $this->insertOrder(['opted_into_marketing_at' => now()->subDays(5)]);
        $second = $this->insertOrder(['email' => 'countess@example.com', 'opted_into_marketing_at' => now()->subDays(5)]);
        $this->projectOrders($first, $second);
        $contactId = $this->contactId('ada@example.com');
        $contacts = app(CrmContactRepositoryInterface::class);
        app(ConsentRecorder::class)->record($contacts->findById($this->contactId('countess@example.com')), CrmConsentAction::from($action), CrmConsentSource::MANUAL);
        app(MergeContactsHandler::class)->handle($this->organizerId, $contactId, $this->contactId('countess@example.com'));
        $this->assertSame($statusWhileSecondary, $this->contact('ada@example.com')->email_marketing_status);

        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);
        $created = app(CreateContactHandler::class)->handle(new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: 'countess@example.com',
            restore_deleted: true,
        ));

        $this->assertSame($status, $created->getEmailMarketingStatus());

        $this->projectOrders($first, $second, $this->insertOrder(['email' => 'countess@example.com', 'opted_into_marketing_at' => now()->subDay()]));

        $this->assertSame(['countess@example.com'], $this->contactEmails());
        $this->assertSame($status, $this->contact('countess@example.com')->email_marketing_status);
    }

    public function test_recreating_a_suppressed_contact_is_all_or_nothing(): void
    {
        $contactId = $this->insertContact('ada@example.com');
        $this->insertConsent($contactId, 'ada@example.com', 'BOUNCED', now()->subDays(3));
        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);
        $deleted = DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->get()->all();
        $recorder = Mockery::mock(ConsentRecorder::class);
        $recorder->shouldReceive('record')->once()->andThrow(new RuntimeException('The database went away.'));
        $this->app->instance(ConsentRecorder::class, $recorder);
        $dto = new UpsertContactDTO(
            account_id: $this->accountId,
            organizer_id: $this->organizerId,
            first_name: null,
            last_name: null,
            email: 'ada@example.com',
            restore_deleted: true,
        );

        try {
            app(CreateContactHandler::class)->handle($dto);
            $this->fail('The contact was created.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The database went away.', $exception->getMessage());
        }

        $this->assertSame([], $this->contactEmails());
        $this->assertSame(0, DB::table('crm_contact_emails')->where('organizer_id', $this->organizerId)->count());
        $this->assertEquals($deleted, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->get()->all());

        $this->app->forgetInstance(ConsentRecorder::class);
        $this->assertSame('BOUNCED', app(CreateContactHandler::class)->handle($dto)->getEmailMarketingStatus());
        $this->assertSame(0, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_deleting_a_contact_replaces_its_email_on_campaign_recipients_with_a_hash(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $this->projectOrders($this->insertOrder());
        $contactId = $this->contactId('ada@example.com');
        $keptId = $this->insertContact('grace@example.com');
        $otherContactId = $this->insertContact('ada@example.com', organizerId: $otherOrganizerId);
        $campaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subDay()]);
        $otherCampaignId = $this->insertCampaign(['status' => 'SENT', 'sent_at' => now()->subDay()], $otherOrganizerId);
        $recipientId = $this->insertRecipient($campaignId, $contactId, 'ada@example.com', ['status' => 'SENT']);
        $keptRecipientId = $this->insertRecipient($campaignId, $keptId, 'grace@example.com', ['status' => 'SENT']);
        $otherRecipientId = $this->insertRecipient($otherCampaignId, $otherContactId, 'ada@example.com', ['status' => 'SENT'], $otherOrganizerId);

        app(DeletedContactService::class)->delete($this->organizerId, [$contactId]);

        $recipients = DB::table('crm_campaign_recipients')->whereIn('id', [$recipientId, $keptRecipientId, $otherRecipientId])->get()->keyBy('id');
        $this->assertSame(
            [null, 'deleted:'.app(DeletedContactHasher::class)->hash($this->organizerId, 'ada@example.com'), 'SENT'],
            [$recipients[$recipientId]->contact_id, $recipients[$recipientId]->email, $recipients[$recipientId]->status],
        );
        $this->assertSame([$keptId, 'grace@example.com'], [(int) $recipients[$keptRecipientId]->contact_id, $recipients[$keptRecipientId]->email]);
        $this->assertSame([$otherContactId, 'ada@example.com'], [(int) $recipients[$otherRecipientId]->contact_id, $recipients[$otherRecipientId]->email]);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('email', 'ada@example.com')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_syncing_an_order_projects_it_with_its_tickets_and_check_ins_for_a_tracked_organizer(): void
    {
        $orderId = $this->insertOrder();
        $this->insertCheckIn($this->insertAttendee($orderId));

        app(ContactSyncService::class)->syncOrder($orderId);

        $this->assertSame(['CHECKED_IN', 'ORDER_COMPLETED', 'TICKET_HELD'], $this->activityTypes('ada@example.com'));
    }

    public function test_an_order_attendee_or_check_in_resolves_to_its_order_only_while_the_organizer_is_tracked(): void
    {
        $orderId = $this->insertOrder();
        $attendeeId = $this->insertAttendee($orderId);
        $checkInId = $this->insertCheckIn($attendeeId);
        $states = app(CrmOrganizerStateRepositoryInterface::class);

        $this->assertSame(
            [$orderId, $orderId, $orderId, null, null],
            [
                $states->findTrackedOrderId(CrmActivitySource::ORDER, $orderId),
                $states->findTrackedOrderId(CrmActivitySource::ATTENDEE, $attendeeId),
                $states->findTrackedOrderId(CrmActivitySource::CHECK_IN, $checkInId),
                $states->findTrackedOrderId(CrmActivitySource::ORDER, 999_999_999),
                $states->findTrackedOrderId(CrmActivitySource::WAITLIST_ENTRY, $this->insertWaitlistEntry()),
            ],
        );

        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->delete();

        $this->assertNull($states->findTrackedOrderId(CrmActivitySource::ATTENDEE, $attendeeId));
    }

    public function test_syncing_does_nothing_without_a_licence(): void
    {
        $this->useLicenceKey(null);

        app(ContactSyncService::class)->syncOrder($this->insertOrder());

        $this->assertSame([], $this->contactEmails());
    }

    public function test_syncing_an_unknown_order_is_a_no_op(): void
    {
        $this->assertTrue(app(ContactSyncService::class)->syncOrder(999_999_999));

        $this->assertSame([], $this->contactEmails());
    }

    public function test_reconciliation_requeues_everything_that_changed_since_the_last_run_and_advances_the_cursor(): void
    {
        Bus::fake([ProjectOrdersJob::class, ProjectWaitlistEntriesJob::class]);
        $since = now()->subHour()->startOfSecond();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['synced_at' => $since, 'reconciled_until' => $since]);

        $stale = ['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)];
        $this->insertOrder($stale);
        $updatedOrder = $this->insertOrder(['updated_at' => now()->subMinutes(10)]);
        $deletedOrder = $this->insertOrder([...$stale, 'deleted_at' => now()->subMinutes(10)]);
        $expiredReservation = $this->insertOrder([...$stale, 'status' => 'RESERVED', 'reserved_until' => now()->subMinutes(5)]);
        $withUpdatedAttendee = $this->insertOrder($stale);
        $this->insertAttendee($withUpdatedAttendee, ['updated_at' => now()->subMinutes(10)]);
        $withNewCheckIn = $this->insertOrder($stale);
        $this->insertCheckIn($this->insertAttendee($withNewCheckIn), ['created_at' => now()->subMinutes(10)]);
        $this->insertOrder(['updated_at' => now()]);
        $this->insertWaitlistEntry();
        $updatedEntry = $this->insertWaitlistEntry(['email' => 'wanda@example.com', 'updated_at' => now()->subMinutes(10)]);

        app()->call([new ReconcileContactsJob, 'handle']);

        Bus::assertDispatchedTimes(ProjectOrdersJob::class, 1);
        Bus::assertDispatched(ProjectOrdersJob::class, function (ProjectOrdersJob $job) use ($updatedOrder, $deletedOrder, $expiredReservation, $withUpdatedAttendee, $withNewCheckIn) {
            return $job->organizerId === $this->organizerId
                && $job->accountId === $this->accountId
                && $job->orderIds === [$updatedOrder, $deletedOrder, $expiredReservation, $withUpdatedAttendee, $withNewCheckIn];
        });
        Bus::assertDispatched(ProjectWaitlistEntriesJob::class, static fn (ProjectWaitlistEntriesJob $job) => $job->entryIds === [$updatedEntry]);

        $reconciledUntil = DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('reconciled_until');
        $this->assertGreaterThan($since->toDateTimeString(), $reconciledUntil);
        $this->assertLessThan(now()->toDateTimeString(), $reconciledUntil);
    }

    public function test_reconciliation_skips_organizers_whose_account_lost_the_crm_flag(): void
    {
        Bus::fake([ProjectOrdersJob::class, ProjectWaitlistEntriesJob::class]);
        $since = now()->subHour()->startOfSecond();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['synced_at' => $since, 'reconciled_until' => $since]);
        $this->insertOrder(['updated_at' => now()->subMinutes(10)]);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, false);

        app()->call([new ReconcileContactsJob, 'handle']);

        Bus::assertNotDispatched(ProjectOrdersJob::class, fn (ProjectOrdersJob $job) => $job->organizerId === $this->organizerId);
        $this->assertSame(
            $since->toDateTimeString(),
            DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->value('reconciled_until'),
        );
    }

    public function test_reconciliation_removes_the_waitlist_activity_of_an_event_deleted_since_the_last_run(): void
    {
        $since = now()->subHour()->startOfSecond();
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['synced_at' => $since, 'reconciled_until' => $since]);
        $keptEventId = $this->createEvent($this->accountId, $this->organizerId, 'Kept Event');
        $keptOccurrenceId = $this->insertOccurrence($keptEventId, now()->addDays(20));
        [, $keptPriceId] = $this->insertTicket($keptEventId);
        $waitlistOnly = $this->insertWaitlistEntry(['email' => 'wanda@example.com']);
        $onDeletedEvent = $this->insertWaitlistEntry();
        $onKeptEvent = $this->insertWaitlistEntry(['event_id' => $keptEventId, 'event_occurrence_id' => $keptOccurrenceId, 'product_price_id' => $keptPriceId]);
        $this->projectWaitlistEntries($waitlistOnly, $onDeletedEvent, $onKeptEvent);
        $this->assertSame(['ada@example.com', 'wanda@example.com'], $this->contactEmails());
        $this->assertCount(2, $this->activities('ada@example.com', 'WAITLIST_JOINED'));

        DB::table('events')->where('id', $this->eventId)->update(['deleted_at' => now()->subMinutes(10)]);
        $projectionRepository = app(CrmProjectionRepositoryInterface::class);
        $this->assertSame(
            [$waitlistOnly, $onDeletedEvent],
            $projectionRepository->findWaitlistEntryIdsChangedSince($this->organizerId, $since, now()->subSeconds(30), 0, 100),
        );
        $this->assertSame([], $projectionRepository->findWaitlistEntryIdsChangedSince($this->organizerId, now()->subMinutes(5), now()->subSeconds(30), 0, 100));

        ReconcileContactsJob::dispatchSync();

        $this->assertSame(['ada@example.com'], $this->contactEmails());
        $this->assertSame([$onKeptEvent], $this->activities('ada@example.com')->pluck('source_id')->map(static fn ($id) => (int) $id)->all());
    }

    public function test_reconciliation_settles_an_initial_sync_whose_batch_finished_without_anyone_polling(): void
    {
        $finishedUuid = $this->insertBatch(finished: true);
        $runningUuid = $this->insertBatch(finished: false);
        $runningOrganizerId = $this->createOrganizer($this->accountId, 'Still Syncing');
        $this->trackOrganizer($this->accountId, $runningOrganizerId);
        DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->update(['synced_at' => null, 'sync_job_uuid' => $finishedUuid]);
        DB::table('crm_organizer_states')->where('organizer_id', $runningOrganizerId)->update(['synced_at' => null, 'sync_job_uuid' => $runningUuid]);

        ReconcileContactsJob::dispatchSync();

        $settled = DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->sole();
        $running = DB::table('crm_organizer_states')->where('organizer_id', $runningOrganizerId)->sole();
        $this->assertNotNull($settled->synced_at);
        $this->assertNull($settled->sync_job_uuid);
        $this->assertSame([null, $runningUuid], [$running->synced_at, $running->sync_job_uuid]);
    }

    public static function untrackedOrganizers(): array
    {
        return [
            'the organizer never started the audience sync' => [false, true],
            'the account does not have the crm flag' => [true, false],
        ];
    }

    #[DataProvider('untrackedOrganizers')]
    public function test_projecting_orders_or_waitlist_entries_does_nothing_for_an_organizer_that_is_not_tracked(bool $hasState, bool $hasFlag): void
    {
        $orderId = $this->insertOrder();
        $this->insertAttendee($orderId);
        $entryId = $this->insertWaitlistEntry(['email' => 'wanda@example.com']);
        if (! $hasState) {
            DB::table('crm_organizer_states')->where('organizer_id', $this->organizerId)->delete();
        }
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, $hasFlag);

        $this->projectOrders($orderId);
        $this->projectWaitlistEntries($entryId);

        $this->assertSame([], $this->contactEmails());
        $this->assertSame(0, DB::table('crm_contact_activities')->where('organizer_id', $this->organizerId)->count());
        $this->assertSame(0, DB::table('crm_consent_events')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_a_twenty_character_order_locale_is_copied_to_the_contact(): void
    {
        $locale = 'zh-Hant-TW-x-private';
        $this->projectOrders($this->insertOrder(['locale' => $locale]));

        $this->assertSame($locale, $this->contact('ada@example.com')->locale);
    }

    private function insertBatch(bool $finished): string
    {
        $uuid = (string) Str::uuid();

        DB::table('job_batches')->insert([
            'id' => $uuid,
            'name' => 'crm-contact-sync',
            'total_jobs' => 1,
            'pending_jobs' => $finished ? 0 : 1,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => serialize([]),
            'created_at' => now()->subMinutes(5)->getTimestamp(),
            'finished_at' => $finished ? now()->subMinute()->getTimestamp() : null,
        ]);

        return $uuid;
    }

    private function recordConsent(int $contactId, CrmConsentAction $action): void
    {
        app(CrmContactRepositoryInterface::class)->insertConsentEvent(
            organizerId: $this->organizerId,
            contactId: $contactId,
            email: 'ada@example.com',
            action: $action,
            source: CrmConsentSource::MANUAL,
            sourceId: null,
            userId: null,
        );
    }

    /**
     * @return array{contacts: array, activities: array, spend: array, consent: array}
     */
    private function projectionSnapshot(): array
    {
        $rows = fn (string $table, array $columns, array $order) => DB::table($table)
            ->where('organizer_id', $this->organizerId)
            ->orderBy($order[0])
            ->orderBy($order[1])
            ->orderBy($order[2] ?? $order[1])
            ->get($columns)
            ->map(static fn (object $row) => (array) $row)
            ->all();

        return [
            'contacts' => $rows('crm_contacts', ['*'], ['id', 'id']),
            'activities' => $rows(
                'crm_contact_activities',
                ['contact_id', 'type', 'role', 'source_type', 'source_id', 'event_id', 'event_occurrence_id', 'order_id', 'product_id', 'promo_code_id', 'label', 'email', 'first_name', 'last_name', 'amount', 'currency', 'occurred_at'],
                ['contact_id', 'type', 'source_id'],
            ),
            'spend' => $rows('crm_contact_spend', ['contact_id', 'currency', 'total_gross', 'total_refunded'], ['contact_id', 'currency']),
            'consent' => $rows('crm_consent_events', ['contact_id', 'email', 'action', 'source_type', 'source_id', 'user_id', 'occurred_at'], ['contact_id', 'source_type', 'source_id']),
        ];
    }
}
