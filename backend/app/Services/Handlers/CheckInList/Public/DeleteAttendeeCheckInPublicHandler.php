<?php

namespace HiEvents\Services\Handlers\CheckInList\Public;

use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Services\Domain\CheckInList\DeleteAttendeeCheckInService;
use HiEvents\Services\Handlers\CheckInList\Public\DTO\DeleteAttendeeCheckInPublicDTO;
use Psr\Log\LoggerInterface;

class DeleteAttendeeCheckInPublicHandler
{
    public function __construct(
        private readonly DeleteAttendeeCheckInService $deleteAttendeeCheckInService,
        private readonly LoggerInterface              $logger,
    )
    {
    }

    /**
     * @throws CannotCheckInException
     */
    public function handle(DeleteAttendeeCheckInPublicDTO $checkInData): void
    {
        $this->deleteAttendeeCheckInService->deleteAttendeeCheckIn(
            $checkInData->checkInListShortId,
            $checkInData->checkInShortId,
        );

        $this->logger->info('Attendee check-in deleted', [
            'check_in_list_uuid' => $checkInData->checkInListShortId,
            'attendee_public_id' => $checkInData->checkInShortId,
            'check_in_user_ip_address' => $checkInData->checkInUserIpAddress,
        ]);
    }
}
