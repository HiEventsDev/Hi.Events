<?php

namespace HiEvents\Jobs\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventSpamCheckStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Mail\Admin\EventFlaggedAsSpamMail;
use HiEvents\Mail\Event\EventPendingManualReviewMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpamCheckRepositoryInterface;
use HiEvents\Services\Domain\Event\DTO\EventSpamCheckResultDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EventSpamCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly int $eventId,
        private readonly string $contentHash,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(
        EventRepositoryInterface $eventRepository,
        EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
        AccountRepositoryInterface $accountRepository,
        EventSpamCheckService $eventSpamCheckService,
        Mailer $mailer,
        Repository $config,
        DatabaseManager $databaseManager,
    ): void {
        if (! $eventSpamCheckService->isEnabled()) {
            return;
        }

        /** @var EventDomainObject|null $event */
        $event = $eventRepository
            ->loadRelation(new Relationship(domainObject: OrganizerDomainObject::class, name: 'organizer'))
            ->findFirstWhere(['id' => $this->eventId]);

        if ($event === null || $event->getStatus() !== EventStatus::LIVE->name) {
            return;
        }

        if ($eventSpamCheckService->hashContent($event->getTitle(), $event->getDescription()) !== $this->contentHash) {
            return;
        }

        $existingCheck = $eventSpamCheckRepository->findFirstWhere([
            'event_id' => $this->eventId,
            'content_hash' => $this->contentHash,
        ]);

        $vettedStatuses = [EventSpamCheckStatus::CLEAN->name, EventSpamCheckStatus::APPROVED->name];

        if ($existingCheck !== null && in_array($existingCheck->getStatus(), $vettedStatuses, true)) {
            return;
        }

        $result = $eventSpamCheckService->checkContent($event->getTitle(), $event->getDescription());

        if (! $result->isSpam) {
            $this->storeCheck($eventSpamCheckRepository, $result, EventSpamCheckStatus::CLEAN);

            return;
        }

        $databaseManager->transaction(function () use (
            $eventRepository,
            $eventSpamCheckRepository,
            $accountRepository,
            $mailer,
            $config,
            $event,
            $result,
        ) {
            $updated = $eventRepository->updateWhere(
                attributes: ['status' => EventStatus::PENDING_MANUAL_REVIEW->name],
                where: [
                    'id' => $this->eventId,
                    'status' => EventStatus::LIVE->name,
                ],
            );

            if ($updated === 0) {
                return;
            }

            $this->storeCheck($eventSpamCheckRepository, $result, EventSpamCheckStatus::FLAGGED);

            $organizerEmail = $event->getOrganizer()?->getEmail();

            if ($organizerEmail) {
                $mailer->to($organizerEmail)->send(new EventPendingManualReviewMail($event));
            }

            $supportEmail = $config->get('app.platform_support_email');

            if ($supportEmail) {
                $account = $accountRepository->findByEventId($event->getId());

                $mailer->to($supportEmail)->send(
                    new EventFlaggedAsSpamMail($event, $account, $result->toVerdictArray())
                );
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Event spam check did not complete', [
            'eventId' => $this->eventId,
            'exception' => $exception->getMessage(),
        ]);
    }

    private function storeCheck(
        EventSpamCheckRepositoryInterface $eventSpamCheckRepository,
        EventSpamCheckResultDTO $result,
        EventSpamCheckStatus $status,
    ): void {
        $eventSpamCheckRepository->create([
            'event_id' => $this->eventId,
            'status' => $status->name,
            'verdict' => $result->toVerdictArray(),
            'content_hash' => $this->contentHash,
            'checked_at' => now(),
        ]);
    }
}
