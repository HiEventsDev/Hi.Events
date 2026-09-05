<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Event;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSpamCheckDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Jobs\Event\EventSpamCheckJob;
use HiEvents\Mail\Admin\EventFlaggedAsSpamMail;
use HiEvents\Mail\Event\EventPendingManualReviewMail;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Services\Domain\Event\DTO\EventSpamCheckResultDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class EventSpamCheckJobTest extends TestCase
{
    private const CONTENT_HASH = 'matching-hash';

    private EventRepositoryInterface|MockInterface $eventRepository;

    private EventSpamCheckRepositoryInterface|MockInterface $eventSpamCheckRepository;

    private AccountRepositoryInterface|MockInterface $accountRepository;

    private EventSpamCheckService|MockInterface $eventSpamCheckService;

    private Mailer|MockInterface $mailer;

    private DatabaseManager|MockInterface $databaseManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->eventSpamCheckRepository = Mockery::mock(EventSpamCheckRepositoryInterface::class);
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->eventSpamCheckService = Mockery::mock(EventSpamCheckService::class);
        $this->mailer = Mockery::mock(Mailer::class);
        $this->databaseManager = Mockery::mock(DatabaseManager::class);
        $this->databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_does_nothing_when_disabled(): void
    {
        $this->expectNotToPerformAssertions();

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnFalse();
        $this->eventRepository->shouldNotReceive('findFirstWhere');

        $this->runJob();
    }

    public function test_skips_when_event_not_found(): void
    {
        $this->expectNotToPerformAssertions();

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();
        $this->eventRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->eventSpamCheckService->shouldNotReceive('checkContent');

        $this->runJob();
    }

    public function test_skips_when_event_no_longer_live(): void
    {
        $this->expectNotToPerformAssertions();

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();
        $this->eventRepository->shouldReceive('findFirstWhere')->andReturn(
            $this->makeEvent(EventStatus::DRAFT->name),
        );
        $this->eventSpamCheckService->shouldNotReceive('checkContent');

        $this->runJob();
    }

    public function test_skips_when_content_changed_since_dispatch(): void
    {
        $this->expectNotToPerformAssertions();

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();
        $this->eventRepository->shouldReceive('findFirstWhere')->andReturn(
            $this->makeEvent(EventStatus::LIVE->name),
        );
        $this->eventSpamCheckService->shouldReceive('hashContent')->andReturn('different-hash');
        $this->eventSpamCheckService->shouldNotReceive('checkContent');

        $this->runJob();
    }

    public function test_skips_llm_call_for_previously_vetted_content(): void
    {
        $this->expectNotToPerformAssertions();

        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();
        $this->eventRepository->shouldReceive('findFirstWhere')->andReturn(
            $this->makeEvent(EventStatus::LIVE->name),
        );
        $this->eventSpamCheckService->shouldReceive('hashContent')->andReturn(self::CONTENT_HASH);
        $this->eventSpamCheckRepository->shouldReceive('findFirstWhere')->andReturn(
            (new EventSpamCheckDomainObject)->setStatus(EventSpamCheckStatus::APPROVED->name),
        );
        $this->eventSpamCheckService->shouldNotReceive('checkContent');

        $this->runJob();
    }

    public function test_stores_clean_result_without_demoting_event(): void
    {
        $this->expectNotToPerformAssertions();

        $this->arrangeCheckableEvent();
        $this->eventSpamCheckService->shouldReceive('checkContent')->andReturn(
            $this->makeResult(isSpam: false),
        );

        $this->eventSpamCheckRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($attrs) => $attrs['status'] === EventSpamCheckStatus::CLEAN->name));

        $this->eventRepository->shouldNotReceive('updateWhere');
        $this->mailer->shouldNotReceive('to');

        $this->runJob();
    }

    public function test_flags_spam_demotes_event_and_sends_both_mails(): void
    {
        $this->expectNotToPerformAssertions();

        $this->arrangeCheckableEvent();
        $this->eventSpamCheckService->shouldReceive('checkContent')->andReturn(
            $this->makeResult(isSpam: true),
        );

        $this->eventRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                ['status' => EventStatus::PENDING_MANUAL_REVIEW->name],
                ['id' => 1, 'status' => EventStatus::LIVE->name],
            )
            ->andReturn(1);

        $this->eventSpamCheckRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($attrs) => $attrs['status'] === EventSpamCheckStatus::FLAGGED->name));

        $this->accountRepository->shouldReceive('findByEventId')->andReturn(new AccountDomainObject);

        $this->expectMail('organizer@example.com', EventPendingManualReviewMail::class);
        $this->expectMail('support@example.com', EventFlaggedAsSpamMail::class);

        $this->runJob();
    }

    public function test_lost_demotion_race_skips_row_and_mails(): void
    {
        $this->expectNotToPerformAssertions();

        $this->arrangeCheckableEvent();
        $this->eventSpamCheckService->shouldReceive('checkContent')->andReturn(
            $this->makeResult(isSpam: true),
        );

        $this->eventRepository->shouldReceive('updateWhere')->once()->andReturn(0);
        $this->eventSpamCheckRepository->shouldNotReceive('create');
        $this->mailer->shouldNotReceive('to');

        $this->runJob();
    }

    public function test_sends_only_organizer_mail_when_support_email_unset(): void
    {
        $this->expectNotToPerformAssertions();

        $this->arrangeCheckableEvent(supportEmail: null);
        $this->eventSpamCheckService->shouldReceive('checkContent')->andReturn(
            $this->makeResult(isSpam: true),
        );

        $this->eventRepository->shouldReceive('updateWhere')->once()->andReturn(1);
        $this->eventSpamCheckRepository->shouldReceive('create')->once();

        $this->expectMail('organizer@example.com', EventPendingManualReviewMail::class);
        $this->accountRepository->shouldNotReceive('findByEventId');

        $this->runJob();
    }

    private function arrangeCheckableEvent(?string $supportEmail = 'support@example.com'): void
    {
        $this->eventSpamCheckService->shouldReceive('isEnabled')->andReturnTrue();
        $this->eventRepository->shouldReceive('findFirstWhere')->andReturn(
            $this->makeEvent(EventStatus::LIVE->name),
        );
        $this->eventSpamCheckService->shouldReceive('hashContent')->andReturn(self::CONTENT_HASH);
        $this->eventSpamCheckRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->supportEmail = $supportEmail;
    }

    private ?string $supportEmail = 'support@example.com';

    private function runJob(): void
    {
        $job = new EventSpamCheckJob(1, self::CONTENT_HASH);

        $job->handle(
            $this->eventRepository,
            $this->eventSpamCheckRepository,
            $this->accountRepository,
            $this->eventSpamCheckService,
            $this->mailer,
            new Repository(['app' => ['platform_support_email' => $this->supportEmail]]),
            $this->databaseManager,
        );
    }

    private function makeEvent(string $status): EventDomainObject
    {
        return (new EventDomainObject)
            ->setId(1)
            ->setStatus($status)
            ->setTitle('Event Title')
            ->setDescription('Event description')
            ->setOrganizer((new OrganizerDomainObject)->setEmail('organizer@example.com'));
    }

    private function makeResult(bool $isSpam): EventSpamCheckResultDTO
    {
        return new EventSpamCheckResultDTO(
            isSpam: $isSpam,
            confidence: $isSpam ? 0.95 : 0.1,
            reasons: $isSpam ? ['Scam content'] : [],
            model: 'claude-haiku-4-5',
        );
    }

    private function expectMail(string $recipient, string $mailableClass): void
    {
        $pendingMail = Mockery::mock();
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(Mockery::type($mailableClass));

        $this->mailer->shouldReceive('to')->once()->with($recipient)->andReturn($pendingMail);
    }
}
