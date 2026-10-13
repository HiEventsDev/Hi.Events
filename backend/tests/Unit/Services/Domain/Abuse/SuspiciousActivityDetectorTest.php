<?php

namespace Tests\Unit\Services\Domain\Abuse;

use Carbon\CarbonImmutable;
use HiEvents\Repository\Interfaces\SuspiciousActivityRepositoryInterface;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO;
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
        $this->assertSame(['390x "Your crypto purchase" (@gmail.com)'], $finding->details);
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
            (object) ['id' => 9746, 'name' => 'Your crypto purchase USD 364 success via Geek Squad'],
        ]));
        $this->repository->shouldReceive('findUsersWithSuspiciousNames')->andReturn(collect([
            (object) ['id' => 14738, 'name' => 'Call 805 891 5965'],
        ]));

        $finding = $this->singleFinding();

        $this->assertSame('suspicious_names', $finding->type);
        $this->assertContains('organizer 9746: "Your crypto purchase USD 364 success via Geek Squad"', $finding->details);
        $this->assertSame('suspicious_names|organizer:9746,user:14738', $finding->fingerprint);
    }

    public function test_flags_bulk_attendees_on_unpublished_events(): void
    {
        $this->repository->shouldReceive('findUnpublishedEventsWithOrderBursts')->andReturn(collect([
            (object) ['event_id' => 11732, 'account_id' => 12288, 'status' => 'PENDING_MANUAL_REVIEW', 'attendees' => 40, 'distinct_emails' => 38, 'orders' => 16],
        ]));

        $finding = $this->singleFinding();

        $this->assertSame('unpublished_event_attendees', $finding->type);
        $this->assertStringContainsString('event 11732', $finding->details[0]);
    }

    public function test_only_checks_for_unchecked_live_events_when_spam_checks_are_enabled(): void
    {
        $this->repository->shouldReceive('findLiveEventsWithoutSpamCheck')->andReturn(collect([
            (object) ['id' => 1, 'account_id' => 2, 'title' => 'Event'],
        ]));

        $this->assertCount(0, $this->detector->detect($this->now));

        $this->spamCheckService->shouldReceive('isEnabled')->andReturnTrue();

        $this->assertSame('unchecked_live_events', $this->singleFinding()->type);
    }

    public function test_flags_email_complaints_and_account_email_volume(): void
    {
        $this->repository->shouldReceive('findEmailCategoryStats')->andReturn(collect([
            (object) ['category' => 'confirm_email_address', 'total' => 900, 'bounced' => 20, 'complained' => 5],
            (object) ['category' => 'attendee_ticket', 'total' => 5000, 'bounced' => 10, 'complained' => 0],
        ]));

        $findings = $this->detector->detect($this->now);

        $this->assertSame(['email_bounces_or_complaints', 'email_volume_spike'], $findings->pluck('type')->all());
        $this->assertSame(['confirm_email_address: 900 sent'], $findings->last()->details);
    }

    private function singleFinding(): SuspiciousActivityFindingDTO
    {
        /** @var Collection<int, SuspiciousActivityFindingDTO> $findings */
        $findings = $this->detector->detect($this->now);

        $this->assertCount(1, $findings);

        return $findings->first();
    }
}
