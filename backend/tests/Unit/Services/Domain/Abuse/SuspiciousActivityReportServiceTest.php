<?php

namespace Tests\Unit\Services\Domain\Abuse;

use HiEvents\Mail\Admin\SuspiciousActivityReportMail;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO;
use HiEvents\Services\Domain\Abuse\SuspiciousActivityDetector;
use HiEvents\Services\Domain\Abuse\SuspiciousActivityReportService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\TestCase;

class SuspiciousActivityReportServiceTest extends TestCase
{
    private SuspiciousActivityDetector|MockInterface $detector;

    private SuspiciousActivityReportService $service;

    private CacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->detector = Mockery::mock(SuspiciousActivityDetector::class);
        $this->service = new SuspiciousActivityReportService(
            $this->detector,
            $this->cache = new CacheRepository(new ArrayStore),
            Mail::getFacadeRoot(),
            new Repository(['app' => ['platform_support_email' => 'support@hi.events']]),
            new NullLogger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_emails_new_findings_to_platform_support(): void
    {
        $this->detector->shouldReceive('detect')->andReturn(collect([$this->finding('a')]));

        $this->assertCount(1, $this->service->reportNewFindings());

        Mail::assertQueued(SuspiciousActivityReportMail::class, fn ($mail) => $mail->hasTo('support@hi.events'));
    }

    public function test_reports_each_finding_only_once(): void
    {
        $this->detector->shouldReceive('detect')->andReturn(
            collect([$this->finding('a')]),
            collect([$this->finding('a'), $this->finding('b')]),
        );

        $this->service->reportNewFindings();
        $second = $this->service->reportNewFindings();

        $this->assertSame(['b'], $second->pluck('fingerprint')->all());
        Mail::assertQueued(SuspiciousActivityReportMail::class, 2);
    }

    public function test_a_finding_whose_alert_failed_to_send_is_reported_again(): void
    {
        $this->detector->shouldReceive('detect')->andReturn(collect([$this->finding('a')]));

        $failingMailer = Mockery::mock(Mailer::class);
        $failingMailer->shouldReceive('to->send')->andThrow(new RuntimeException('Queue unavailable'));

        $failingService = new SuspiciousActivityReportService(
            $this->detector,
            $this->cache,
            $failingMailer,
            new Repository(['app' => ['platform_support_email' => 'support@hi.events']]),
            new NullLogger,
        );

        try {
            $failingService->reportNewFindings();
            $this->fail('Expected the alert to fail');
        } catch (RuntimeException) {
        }

        $this->assertCount(1, $this->service->reportNewFindings());
        Mail::assertQueued(SuspiciousActivityReportMail::class, 1);
    }

    public function test_sends_nothing_when_there_is_nothing_new(): void
    {
        $this->detector->shouldReceive('detect')->andReturn(collect());

        $this->service->reportNewFindings();

        Mail::assertNothingQueued();
    }

    private function finding(string $fingerprint): SuspiciousActivityFindingDTO
    {
        return new SuspiciousActivityFindingDTO(
            type: 'signup_spike',
            summary: 'Spike',
            details: [],
            fingerprint: $fingerprint,
        );
    }
}
