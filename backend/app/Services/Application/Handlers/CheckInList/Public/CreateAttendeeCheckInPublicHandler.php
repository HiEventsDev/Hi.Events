<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\Enums\WebhookEventType;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\CreateAttendeeCheckInPublicDTO;
use HiEvents\Services\Domain\CheckInList\CreateAttendeeCheckInService;
use HiEvents\Services\Domain\CheckInList\DTO\CreateAttendeeCheckInsResponseDTO;
use HiEvents\Services\Infrastructure\Webhook\WebhookDispatchService;
use Psr\Log\LoggerInterface;
use Throwable;

class CreateAttendeeCheckInPublicHandler
{
    public function __construct(
        private readonly CreateAttendeeCheckInService $createAttendeeCheckInService,
        private readonly LoggerInterface              $logger,
        private readonly WebhookDispatchService       $webhookDispatchService,
    )
    {
    }

    /**
     * @throws CannotCheckInException|Throwable
     */
    public function handle(CreateAttendeeCheckInPublicDTO $checkInData): CreateAttendeeCheckInsResponseDTO
    {
        $checkIns = $this->createAttendeeCheckInService->checkInAttendees(
            $checkInData->checkInListUuid,
            $checkInData->checkInUserIpAddress,
            $checkInData->attendeesAndActions,
        );

        $this->logger->info('Attendee check-ins created', [
            'attendee_ids' => $checkIns->attendeeCheckIns
                ->map(fn(AttendeeCheckInDomainObject $checkIn) => $checkIn->getAttendeeId())->toArray(),
            'check_in_list_uuid' => $checkInData->checkInListUuid,
            'ip_address' => $checkInData->checkInUserIpAddress,
        ]);

        /** @var AttendeeCheckInDomainObject $checkIn */
        foreach ($checkIns->attendeeCheckIns as $checkIn) {
            $this->webhookDispatchService->queueCheckInWebhook(
                WebhookEventType::CHECKIN_CREATED,
                $checkIn->getId(),
            );
        }

        return $checkIns;
    }
}
