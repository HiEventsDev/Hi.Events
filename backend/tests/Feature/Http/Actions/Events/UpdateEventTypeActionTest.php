<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Events;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class UpdateEventTypeActionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private User $owner;

    private int $accountId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
    }

    public function test_a_single_event_can_become_recurring_even_with_orders(): void
    {
        $eventId = $this->createTypedEvent(EventType::SINGLE);
        $occurrenceId = $this->createOccurrence($eventId);
        $this->createOrder($eventId, OrderStatus::COMPLETED);

        $this->changeType($eventId, EventType::RECURRING)
            ->assertOk()
            ->assertJsonPath('data.type', EventType::RECURRING->name);

        $this->assertSame(EventType::RECURRING->name, DB::table('events')->where('id', $eventId)->value('type'));
        $this->assertNotNull(DB::table('event_occurrences')->where('id', $occurrenceId)->whereNull('deleted_at')->first());
    }

    public function test_a_recurring_event_with_one_date_and_no_orders_becomes_single(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING, ['frequency' => 'weekly']);
        $occurrenceLocationId = $this->createEventLocation($eventId);
        $occurrenceId = $this->createOccurrence($eventId, [
            'capacity' => 50,
            'label' => 'Matinee',
            'event_location_id' => $occurrenceLocationId,
            'is_overridden' => true,
        ]);
        [$productId, $priceId] = $this->createProduct($eventId);
        DB::table('product_price_occurrence_overrides')->insert([
            'event_occurrence_id' => $occurrenceId,
            'product_price_id' => $priceId,
            'price' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_occurrence_visibility')->insert([
            'event_occurrence_id' => $occurrenceId,
            'product_id' => $productId,
        ]);

        $this->changeType($eventId, EventType::SINGLE)
            ->assertOk()
            ->assertJsonPath('data.type', EventType::SINGLE->name);

        $event = DB::table('events')->where('id', $eventId)->first();
        $this->assertSame(EventType::SINGLE->name, $event->type);
        $this->assertNull($event->recurrence_rule);
        $this->assertSame($occurrenceLocationId, $event->event_location_id);

        $occurrence = DB::table('event_occurrences')->where('id', $occurrenceId)->first();
        $this->assertNull($occurrence->capacity);
        $this->assertNull($occurrence->label);
        $this->assertNull($occurrence->event_location_id);
        $this->assertFalse((bool) $occurrence->is_overridden);

        $this->assertSame(0, DB::table('product_price_occurrence_overrides')->where('event_occurrence_id', $occurrenceId)->count());
        $this->assertSame(0, DB::table('product_occurrence_visibility')->where('event_occurrence_id', $occurrenceId)->count());
    }

    public function test_converting_to_single_makes_ticket_quantities_event_wide(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);
        $this->createOccurrence($eventId);
        [, $priceId] = $this->createProduct($eventId);
        DB::table('product_prices')->where('id', $priceId)->update(['quantity_applies_to' => 'OCCURRENCE']);

        $this->changeType($eventId, EventType::SINGLE)->assertOk();

        $this->assertSame('EVENT', DB::table('product_prices')->where('id', $priceId)->value('quantity_applies_to'));
    }

    public function test_converting_to_single_unscopes_check_in_lists_from_dates(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);
        $occurrenceId = $this->createOccurrence($eventId);
        $deletedOccurrenceId = $this->createOccurrence($eventId, ['deleted_at' => now()]);
        $remainingListId = $this->createCheckInList($eventId, $occurrenceId);
        $orphanedListId = $this->createCheckInList($eventId, $deletedOccurrenceId);

        $this->changeType($eventId, EventType::SINGLE)->assertOk();

        $this->assertNull(DB::table('check_in_lists')->where('id', $remainingListId)->value('event_occurrence_id'));
        $this->assertNull(DB::table('check_in_lists')->where('id', $orphanedListId)->value('event_occurrence_id'));
    }

    public function test_converting_to_recurring_assigns_undated_waitlist_entries_to_the_existing_date(): void
    {
        $eventId = $this->createTypedEvent(EventType::SINGLE);
        $occurrenceId = $this->createOccurrence($eventId);
        [, $priceId] = $this->createProduct($eventId);
        $entryId = DB::table('waitlist_entries')->insertGetId([
            'event_id' => $eventId,
            'product_price_id' => $priceId,
            'email' => 'waiting@example.test',
            'first_name' => 'Waiting',
            'status' => WaitlistEntryStatus::WAITING->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->changeType($eventId, EventType::RECURRING)->assertOk();

        $this->assertSame($occurrenceId, DB::table('waitlist_entries')->where('id', $entryId)->value('event_occurrence_id'));
    }

    public function test_converting_to_single_keeps_the_event_location_when_the_date_has_none(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);
        $eventLocationId = $this->createEventLocation($eventId);
        DB::table('events')->where('id', $eventId)->update(['event_location_id' => $eventLocationId]);
        $this->createOccurrence($eventId);

        $this->changeType($eventId, EventType::SINGLE)->assertOk();

        $this->assertSame($eventLocationId, DB::table('events')->where('id', $eventId)->value('event_location_id'));
    }

    public function test_a_recurring_event_with_several_dates_cannot_become_single(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);
        $this->createOccurrence($eventId);
        $this->createOccurrence($eventId);

        $this->changeType($eventId, EventType::SINGLE)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertSame(EventType::RECURRING->name, DB::table('events')->where('id', $eventId)->value('type'));
    }

    public function test_deleted_dates_do_not_count_towards_the_single_date_limit(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);
        $this->createOccurrence($eventId);
        $this->createOccurrence($eventId, ['deleted_at' => now()]);

        $this->changeType($eventId, EventType::SINGLE)->assertOk();
    }

    public function test_a_recurring_event_with_no_dates_cannot_become_single(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);

        $this->changeType($eventId, EventType::SINGLE)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_a_recurring_event_whose_only_date_is_cancelled_cannot_become_single(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);
        $this->createOccurrence($eventId, ['status' => 'CANCELLED']);

        $this->changeType($eventId, EventType::SINGLE)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    #[DataProvider('blockingOrders')]
    public function test_a_recurring_event_with_orders_cannot_become_single(OrderStatus $status, ?string $reservedUntil): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);
        $this->createOccurrence($eventId);
        $this->createOrder($eventId, $status, $reservedUntil === null ? null : Carbon::parse($reservedUntil));

        $this->changeType($eventId, EventType::SINGLE)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertSame(EventType::RECURRING->name, DB::table('events')->where('id', $eventId)->value('type'));
    }

    public static function blockingOrders(): array
    {
        return [
            'completed' => [OrderStatus::COMPLETED, null],
            'cancelled' => [OrderStatus::CANCELLED, null],
            'awaiting offline payment' => [OrderStatus::AWAITING_OFFLINE_PAYMENT, null],
            'checkout in progress' => [OrderStatus::RESERVED, '+1 hour'],
        ];
    }

    #[DataProvider('ignoredOrders')]
    public function test_abandoned_and_expired_checkouts_do_not_block_becoming_single(OrderStatus $status, ?string $reservedUntil): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING);
        $this->createOccurrence($eventId);
        $this->createOrder($eventId, $status, $reservedUntil === null ? null : Carbon::parse($reservedUntil));

        $this->changeType($eventId, EventType::SINGLE)->assertOk();
    }

    public static function ignoredOrders(): array
    {
        return [
            'abandoned' => [OrderStatus::ABANDONED, null],
            'expired checkout' => [OrderStatus::RESERVED, '-1 hour'],
        ];
    }

    public function test_setting_the_current_type_changes_nothing(): void
    {
        $eventId = $this->createTypedEvent(EventType::RECURRING, ['frequency' => 'weekly']);
        $this->createOccurrence($eventId);
        $this->createOccurrence($eventId);

        $this->changeType($eventId, EventType::RECURRING)->assertOk();

        $this->assertNotNull(DB::table('events')->where('id', $eventId)->value('recurrence_rule'));
    }

    public function test_an_invalid_type_is_rejected(): void
    {
        $eventId = $this->createTypedEvent(EventType::SINGLE);

        $this->putJson("/events/$eventId/type", ['type' => 'WEEKLY'], $this->headersFor($this->owner, $this->accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_another_accounts_event_cannot_be_changed(): void
    {
        [$otherOwner, $otherAccountId] = $this->createAccountOwner();
        $otherEventId = $this->createEvent($otherAccountId, $this->createOrganizer($otherAccountId));

        $this->putJson("/events/$otherEventId/type", ['type' => EventType::RECURRING->name], $this->headersFor($this->owner, $this->accountId))
            ->assertForbidden();

        $this->assertSame(EventType::SINGLE->name, DB::table('events')->where('id', $otherEventId)->value('type'));
    }

    public function test_a_read_only_member_cannot_change_the_type(): void
    {
        $eventId = $this->createTypedEvent(EventType::SINGLE);
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->putJson("/events/$eventId/type", ['type' => EventType::RECURRING->name], $this->headersFor($viewer, $this->accountId))
            ->assertForbidden();

        $this->assertSame(EventType::SINGLE->name, DB::table('events')->where('id', $eventId)->value('type'));
    }

    private function changeType(int $eventId, EventType $type)
    {
        return $this->putJson("/events/$eventId/type", ['type' => $type->name], $this->headersFor($this->owner, $this->accountId));
    }

    private function createTypedEvent(EventType $type, ?array $recurrenceRule = null): int
    {
        $eventId = $this->createEvent($this->accountId, $this->organizerId);

        DB::table('events')->where('id', $eventId)->update([
            'type' => $type->name,
            'recurrence_rule' => $recurrenceRule === null ? null : json_encode($recurrenceRule),
        ]);

        return $eventId;
    }

    private function createOccurrence(int $eventId, array $attributes = []): int
    {
        return DB::table('event_occurrences')->insertGetId(array_merge([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(2),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function createCheckInList(int $eventId, int $occurrenceId): int
    {
        return DB::table('check_in_lists')->insertGetId([
            'short_id' => 'cil_'.uniqid(),
            'name' => 'Door',
            'event_id' => $eventId,
            'event_occurrence_id' => $occurrenceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEventLocation(int $eventId): int
    {
        return DB::table('event_locations')->insertGetId([
            'short_id' => 'el_'.uniqid(),
            'event_id' => $eventId,
            'type' => 'ONLINE',
            'online_event_connection_details' => 'https://example.test/stream',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(int $eventId): array
    {
        $productId = DB::table('products')->insertGetId([
            'title' => 'General Admission',
            'event_id' => $eventId,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $priceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$productId, $priceId];
    }

    private function createOrder(int $eventId, OrderStatus $status, ?Carbon $reservedUntil = null): void
    {
        DB::table('orders')->insert([
            'short_id' => 'o_'.uniqid(),
            'event_id' => $eventId,
            'currency' => 'USD',
            'status' => $status->name,
            'reserved_until' => $reservedUntil,
            'public_id' => 'p_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
