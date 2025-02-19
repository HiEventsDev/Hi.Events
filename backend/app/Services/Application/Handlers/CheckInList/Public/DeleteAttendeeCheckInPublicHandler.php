<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\Enums\WebhookEventType;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\DeleteAttendeeCheckInPublicDTO;
use HiEvents\Services\Domain\CheckInList\DeleteAttendeeCheckInService;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Psr\Log\LoggerInterface;

class DeleteAttendeeCheckInPublicHandler
{
    public function __construct(
        private readonly DeleteAttendeeCheckInService $deleteAttendeeCheckInService,
        private readonly LoggerInterface              $logger,
        private readonly WebhookDispatchService       $webhookDispatchService,
    )
    {
    }

    /**
     * @throws CannotCheckInException
     */
    public function handle(DeleteAttendeeCheckInPublicDTO $checkInData): void
    {
        $deletedCheckInId = $this->deleteAttendeeCheckInService->deleteAttendeeCheckIn(
            $checkInData->checkInListShortId,
            $checkInData->checkInShortId,
        );

        $this->logger->info('Attendee check-in deleted', [
            'check_in_list_uuid' => $checkInData->checkInListShortId,
            'attendee_public_id' => $checkInData->checkInShortId,
            'check_in_user_ip_address' => $checkInData->checkInUserIpAddress,
        ]);

        $this->webhookDispatchService->queueCheckInWebhook(
            eventType: WebhookEventType::CHECKIN_DELETED,
            attendeeCheckInId: $deletedCheckInId,
        );
    }
}
