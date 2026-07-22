<?php

namespace HiEvents\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\DeleteAttendeeCheckInPublicDTO;
use HiEvents\Services\Domain\CheckInList\CheckInListDataService;
use HiEvents\Services\Domain\CheckInList\DeleteAttendeeCheckInService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\CheckinEvent;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class DeleteAttendeeCheckInPublicHandler
{
    public function __construct(
        private readonly DeleteAttendeeCheckInService $deleteAttendeeCheckInService,
        private readonly LoggerInterface              $logger,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly DatabaseManager              $databaseManager,
        private readonly CheckInListDataService       $checkInListDataService,
    )
    {
    }

    /**
     * @throws CannotCheckInException
     * @throws Throwable
     */
    public function handle(DeleteAttendeeCheckInPublicDTO $checkInData): void
    {
        $checkInList = $this->checkInListDataService->getCheckInList($checkInData->checkInListShortId);
        $this->validateCheckInListIsAuthorized($checkInList, $checkInData->password);

        $this->databaseManager->transaction(function () use ($checkInData) {
            $deletedCheckInId = $this->deleteAttendeeCheckInService->deleteAttendeeCheckIn(
                $checkInData->checkInListShortId,
                $checkInData->checkInShortId,
            );

            $this->logger->info('Attendee check-in deleted', [
                'check_in_list_uuid' => $checkInData->checkInListShortId,
                'attendee_public_id' => $checkInData->checkInShortId,
                'check_in_user_ip_address' => $checkInData->checkInUserIpAddress,
            ]);

            $this->domainEventDispatcherService->dispatch(
                new CheckinEvent(
                    type: DomainEventType::CHECKIN_DELETED,
                    attendeeCheckinId: $deletedCheckInId,
                )
            );
        });
    }

    /**
     * @throws CannotCheckInException
     */
    private function validateCheckInListIsAuthorized(CheckInListDomainObject $checkInList, ?string $password): void
    {
        if ($checkInList->isPasswordProtected() && $checkInList->getPassword() !== $password) {
            throw new CannotCheckInException(__('Invalid password provided'));
        }
    }
}
