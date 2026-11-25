<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\SuspiciousActivityRepository;
use HiEvents\Services\Domain\Abuse\SuspiciousActivityDetector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuspiciousActivityRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private SuspiciousActivityRepository $repository;

    private User $user;

    private int $accountId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(SuspiciousActivityRepository::class);
        $this->user = User::factory()->withAccount()->create();
        $this->accountId = $this->user->accounts()->first()->id;
        $this->organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Abuse Organizer',
            'email' => 'abuse-organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_finds_account_context_with_live_events_and_stripe(): void
    {
        $this->createEvent('LIVE');
        $this->createEvent('LIVE');
        $this->createEvent('DRAFT');
        DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => true]);
        DB::table('organizer_stripe_platforms')->insert([
            'organizer_id' => $this->organizerId,
            'stripe_account_id' => 'acct_test',
            'stripe_setup_completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $context = $this->repository->findAccountContexts([$this->accountId])->get($this->accountId);

        $this->assertNotNull($context);
        $this->assertSame(2, (int) $context->live_events);
        $this->assertTrue((bool) $context->has_stripe);
        $this->assertTrue((bool) $context->is_manually_verified);
        $this->assertNull($context->suspended_at);
    }

    public function test_account_without_a_completed_stripe_setup_has_no_stripe(): void
    {
        DB::table('organizer_stripe_platforms')->insert([
            'organizer_id' => $this->organizerId,
            'stripe_account_id' => 'acct_test',
            'stripe_setup_completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $context = $this->repository->findAccountContexts([$this->accountId])->get($this->accountId);

        $this->assertFalse((bool) $context->has_stripe);
        $this->assertSame(0, (int) $context->live_events);
    }

    public function test_failed_payment_bursts_include_the_event_account_and_title(): void
    {
        $eventId = $this->createEvent('LIVE', 'Card testing target');
        foreach (range(1, 3) as $ignored) {
            DB::table('orders')->insert([
                'short_id' => 'ord_'.uniqid(),
                'event_id' => $eventId,
                'currency' => 'USD',
                'status' => OrderStatus::RESERVED->name,
                'payment_status' => OrderPaymentStatus::PAYMENT_FAILED->name,
                'public_id' => 'pub_'.uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $row = $this->repository->findEventsWithFailedPaymentBursts(now()->subHour(), 3)->firstWhere('event_id', $eventId);

        $this->assertNotNull($row);
        $this->assertSame($this->accountId, (int) $row->account_id);
        $this->assertSame('Card testing target', $row->title);
        $this->assertSame('LIVE', $row->status);
        $this->assertSame(3, (int) $row->total);
    }

    public function test_suspicious_names_include_the_account_they_belong_to(): void
    {
        $eventId = $this->createEvent('LIVE', 'Call 805 891 5965 for crypto refunds');
        DB::table('users')->where('id', $this->user->id)->update(['first_name' => 'Visit www.scam.example']);

        $event = $this->repository
            ->findEventsWithSuspiciousTitles(now()->subHour(), SuspiciousActivityDetector::SUSPICIOUS_TITLE_PATTERN, 50)
            ->firstWhere('id', $eventId);
        $user = $this->repository
            ->findUsersWithSuspiciousNames(now()->subHour(), SuspiciousActivityDetector::SUSPICIOUS_NAME_PATTERN, 50)
            ->firstWhere('id', $this->user->id);

        $this->assertSame($this->accountId, (int) $event->account_id);
        $this->assertSame($this->accountId, (int) $user->account_id);
    }

    private function createEvent(string $status, string $title = 'Event'): int
    {
        return DB::table('events')->insertGetId([
            'title' => $title,
            'status' => $status,
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
