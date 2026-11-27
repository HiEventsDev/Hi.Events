<?php

namespace Tests\Unit\Services\Domain\Abuse;

use Carbon\CarbonImmutable;
use HiEvents\Repository\Interfaces\SuspiciousActivityRepositoryInterface;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityItemDTO;
use HiEvents\Services\Domain\Abuse\SuspiciousActivityDetector;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SuspiciousActivityDetectorTest extends TestCase
{
    private SuspiciousActivityRepositoryInterface|MockInterface $repository;

    private EventSpamCheckService|MockInterface $spamCheckService;

    private SuspiciousActivityDetector $detector;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-10-13 15:00:00');
        $this->repository = Mockery::mock(SuspiciousActivityRepositoryInterface::class);
        $this->spamCheckService = Mockery::mock(EventSpamCheckService::class);
        $this->spamCheckService->shouldReceive('isEnabled')->andReturnFalse()->byDefault();

        foreach ([
            'findRecentAccountNamesAndEmailDomains',
            'findUsersWithSuspiciousNames',
            'findAccountsWithSuspiciousNames',
            'findOrganizersWithSuspiciousNames',
            'findEventsWithSuspiciousTitles',
            'findAccountsWithInviteBursts',
            'findUnpublishedEventsWithOrderBursts',
            'findEventsWithFailedPaymentBursts',
            'findEventsWithWaitlistBursts',
            'findLiveEventsWithoutSpamCheck',
            'findEmailCategoryStats',
            'findAccountContexts',
        ] as $method) {
            $this->repository->shouldReceive($method)->andReturn(collect())->byDefault();
        }

        $this->repository->shouldReceive('countAccountsCreatedBetween')->andReturn(0)->byDefault();

        $this->detector = new SuspiciousActivityDetector($this->repository, $this->spamCheckService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reports_nothing_on_a_quiet_platform(): void
    {
        $this->repository->shouldReceive('countAccountsCreatedBetween')->andReturn(3);

        $this->assertCount(0, $this->detector->detect($this->now));
    }

    public function test_flags_a_signup_spike(): void
    {
        $this->repository->shouldReceive('countAccountsCreatedBetween')
            ->andReturnUsing(fn (CarbonImmutable $from, CarbonImmutable $to) => $to->eq($this->now) ? 400 : 1300);
        $this->repository->shouldReceive('findRecentAccountNamesAndEmailDomains')->andReturn(collect([
            (object) ['total' => 390, 'name' => 'Your crypto purchase', 'email_domain' => 'gmail.com'],
        ]));

        $finding = $this->singleFinding();

        $this->assertSame('signup_spike', $finding->type);
        $this->assertStringContainsString('400 accounts', $finding->summary);
        $this->assertSame('390x "Your crypto purchase" (@gmail.com)', $finding->items[0]->label);
        $this->assertSame('Your crypto purchase', $finding->items[0]->accountSearch);
        $this->assertNotNull($finding->adminPath);
    }

    public function test_does_not_flag_normal_signup_volume(): void
    {
        $this->repository->shouldReceive('countAccountsCreatedBetween')
            ->andReturnUsing(fn (CarbonImmutable $from, CarbonImmutable $to) => $to->eq($this->now) ? 12 : 13000);

        $this->assertCount(0, $this->detector->detect($this->now));
    }

    public function test_flags_suspicious_names_with_a_fingerprint_per_set_of_records(): void
    {
        $this->repository->shouldReceive('findOrganizersWithSuspiciousNames')->andReturn(collect([
            (object) ['id' => 9746, 'account_id' => 501, 'name' => 'Your crypto purchase USD 364 success via Geek Squad'],
        ]));
        $this->repository->shouldReceive('findUsersWithSuspiciousNames')->andReturn(collect([
            (object) ['id' => 14738, 'account_id' => 502, 'name' => 'Call 805 891 5965'],
        ]));

        $finding = $this->singleFinding();

        $this->assertSame('suspicious_names', $finding->type);
        $this->assertContains('Organizer 9746: "Your crypto purchase USD 364 success via Geek Squad"', $this->labels($finding));
        $this->assertEqualsCanonicalizing([501, 502], array_map(fn (SuspiciousActivityItemDTO $item) => $item->accountId, $finding->items));
        $this->assertSame('suspicious_names|organizer:9746,user:14738', $finding->fingerprint);
    }

    public function test_flags_bulk_attendees_on_unpublished_events(): void
    {
        $this->repository->shouldReceive('findUnpublishedEventsWithOrderBursts')->andReturn(collect([
            (object) ['event_id' => 11732, 'account_id' => 12288, 'title' => 'Webinar', 'status' => 'PENDING_MANUAL_REVIEW', 'attendees' => 40, 'distinct_emails' => 38, 'orders' => 16],
        ]));

        $finding = $this->singleFinding();

        $this->assertSame('unpublished_event_attendees', $finding->type);
        $this->assertSame('Event 11732: "Webinar"', $finding->items[0]->label);
        $this->assertSame(['PENDING_MANUAL_REVIEW', '40 attendees, 38 distinct emails, 16 orders'], $finding->items[0]->facts);
        $this->assertSame(12288, $finding->items[0]->accountId);
    }

    public function test_only_checks_for_unchecked_live_events_when_spam_checks_are_enabled(): void
    {
        $this->repository->shouldReceive('findLiveEventsWithoutSpamCheck')->andReturn(collect([
            (object) ['id' => 1, 'account_id' => 2, 'title' => 'Event'],
        ]));

        $this->assertCount(0, $this->detector->detect($this->now));

        $this->spamCheckService->shouldReceive('isEnabled')->andReturnTrue();

        $finding = $this->singleFinding();

        $this->assertSame('unchecked_live_events', $finding->type);
        $this->assertSame('failed-jobs', $finding->adminPath);
    }

    public function test_flags_email_complaints_and_account_email_volume(): void
    {
        $this->repository->shouldReceive('findEmailCategoryStats')->andReturn(collect([
            (object) ['category' => 'confirm_email_address', 'total' => 900, 'bounced' => 20, 'complained' => 5],
            (object) ['category' => 'attendee_ticket', 'total' => 5000, 'bounced' => 10, 'complained' => 0],
        ]));

        $findings = $this->detector->detect($this->now);

        $this->assertSame(['email_bounces_or_complaints', 'email_volume_spike'], $findings->pluck('type')->all());
        $this->assertSame('confirm_email_address', $findings->last()->items[0]->label);
        $this->assertSame(['900 sent'], $findings->last()->items[0]->facts);
        $this->assertSame('email-delivery', $findings->last()->adminPath);
    }

    public function test_attaches_account_context_to_items_with_one_lookup(): void
    {
        $this->repository->shouldReceive('findEventsWithFailedPaymentBursts')->andReturn(collect([
            (object) ['event_id' => 9031, 'account_id' => 412, 'title' => 'Free webinar', 'status' => 'LIVE', 'total' => 14],
        ]));
        $this->repository->shouldReceive('findEventsWithWaitlistBursts')->andReturn(collect([
            (object) ['event_id' => 9032, 'account_id' => 412, 'title' => 'Second webinar', 'status' => 'LIVE', 'total' => 80],
        ]));
        $this->repository->shouldReceive('findAccountContexts')->once()->with([412])->andReturn(collect([
            412 => (object) [
                'id' => 412,
                'name' => 'Fast Help',
                'email' => 'owner@example.com',
                'created_at' => '2026-10-13 12:00:00',
                'is_manually_verified' => false,
                'suspended_at' => null,
                'live_events' => 2,
                'has_stripe' => true,
            ],
        ]));

        $findings = $this->detector->detect($this->now);

        $this->assertSame(['failed_payment_burst', 'waitlist_burst'], $findings->pluck('type')->all());

        $account = $findings->first()->items[0]->account;
        $this->assertSame('Fast Help', $account->name);
        $this->assertSame('owner@example.com', $account->email);
        $this->assertFalse($account->isVerified);
        $this->assertTrue($account->hasStripe);
        $this->assertFalse($account->isSuspended);
        $this->assertSame(2, $account->liveEvents);
        $this->assertSame(412, $findings->last()->items[0]->account->id);
    }

    public function test_skips_the_account_lookup_when_no_finding_names_an_account(): void
    {
        $this->repository->shouldReceive('findEmailCategoryStats')->andReturn(collect([
            (object) ['category' => 'confirm_email_address', 'total' => 900, 'bounced' => 0, 'complained' => 0],
        ]));
        $this->repository->shouldNotReceive('findAccountContexts');

        $this->assertSame('email_volume_spike', $this->singleFinding()->type);
    }

    /**
     * @return array<int, string>
     */
    private function labels(SuspiciousActivityFindingDTO $finding): array
    {
        return array_map(fn (SuspiciousActivityItemDTO $item) => $item->label, $finding->items);
    }

    private function singleFinding(): SuspiciousActivityFindingDTO
    {
        /** @var Collection<int, SuspiciousActivityFindingDTO> $findings */
        $findings = $this->detector->detect($this->now);

        $this->assertCount(1, $findings);

        return $findings->first();
    }
}
