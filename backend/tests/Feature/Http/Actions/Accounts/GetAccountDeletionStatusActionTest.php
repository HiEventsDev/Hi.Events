<?php

namespace Tests\Feature\Http\Actions\Accounts;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class GetAccountDeletionStatusActionTest extends TestCase
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

    public function test_lists_upcoming_events_with_completed_orders_as_blocking(): void
    {
        $blockedEventId = $this->createEvent($this->accountId, $this->organizerId, 'Summer Festival');
        $this->addOccurrence($blockedEventId, now()->addMonth());
        $this->addOrder($blockedEventId, OrderStatus::COMPLETED->name);

        $pastEventId = $this->createEvent($this->accountId, $this->organizerId, 'Last Year');
        $this->addOccurrence($pastEventId, now()->subMonth());
        $this->addOrder($pastEventId, OrderStatus::COMPLETED->name);

        $this->getJson('/accounts/deletion-request', $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->assertJsonPath('data.can_request_deletion', false)
            ->assertJsonPath('data.blocking_events', [
                ['id' => $blockedEventId, 'title' => 'Summer Festival'],
            ]);
    }

    public function test_has_no_blocking_events_when_deletion_is_allowed(): void
    {
        $eventId = $this->createEvent($this->accountId, $this->organizerId);
        $this->addOccurrence($eventId, now()->addMonth());
        $this->addOrder($eventId, OrderStatus::CANCELLED->name);

        $this->getJson('/accounts/deletion-request', $this->headersFor($this->owner, $this->accountId))
            ->assertOk()
            ->assertJsonPath('data.can_request_deletion', true)
            ->assertJsonPath('data.blocking_events', []);
    }

    private function addOccurrence(int $eventId, \DateTimeInterface $startDate): void
    {
        DB::table('event_occurrences')->insert([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => $startDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addOrder(int $eventId, string $status): void
    {
        DB::table('orders')->insert([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $eventId,
            'currency' => 'USD',
            'status' => $status,
            'public_id' => 'PUB_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
