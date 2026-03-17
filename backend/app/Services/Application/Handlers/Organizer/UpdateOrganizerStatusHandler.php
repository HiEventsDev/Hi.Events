<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\UpdateOrganizerStatusDTO;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class UpdateOrganizerStatusHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly AccountRepositoryInterface   $accountRepository,
        private readonly EventRepositoryInterface     $eventRepository,
        private readonly LoggerInterface              $logger,
        private readonly DatabaseManager              $databaseManager,
    )
    {
    }

    /**
     * @throws AccountNotVerifiedException|CannotDeleteEntityException|Throwable
     */
    public function handle(UpdateOrganizerStatusDTO $updateOrganizerStatusDTO): OrganizerDomainObject
    {
        return $this->databaseManager->transaction(function () use ($updateOrganizerStatusDTO) {
            return $this->updateOrganizerStatus($updateOrganizerStatusDTO);
        });
    }

    /**
     * @throws AccountNotVerifiedException|CannotDeleteEntityException
     */
    private function updateOrganizerStatus(UpdateOrganizerStatusDTO $updateOrganizerStatusDTO): OrganizerDomainObject
    {
        $account = $this->accountRepository->findById($updateOrganizerStatusDTO->accountId);

        if ($account->getAccountVerifiedAt() === null) {
            throw new AccountNotVerifiedException(
                __('You must verify your account before you can update an organizer\'s status.
                You can resend the confirmation by visiting your profile page.'),
            );
        }

        if ($updateOrganizerStatusDTO->status === OrganizerStatus::ARCHIVED->name) {
            $activeOrganizerCount = $this->organizerRepository->countWhere([
                'account_id' => $updateOrganizerStatusDTO->accountId,
                ['status', '!=', OrganizerStatus::ARCHIVED->name],
            ]);

            if ($activeOrganizerCount <= 1) {
                throw new CannotDeleteEntityException(
                    __('You cannot archive the last active organizer on your account.')
                );
            }
        }

        $this->organizerRepository->updateWhere(
            attributes: ['status' => $updateOrganizerStatusDTO->status],
            where: [
                'id' => $updateOrganizerStatusDTO->organizerId,
                'account_id' => $updateOrganizerStatusDTO->accountId,
            ]
        );

        if ($updateOrganizerStatusDTO->status === OrganizerStatus::ARCHIVED->name) {
            $this->eventRepository->updateWhere(
                attributes: ['status' => EventStatus::ARCHIVED->name],
                where: [
                    'organizer_id' => $updateOrganizerStatusDTO->organizerId,
                    'account_id' => $updateOrganizerStatusDTO->accountId,
                ]
            );

            $this->logger->info('All events archived for organizer', [
                'organizerId' => $updateOrganizerStatusDTO->organizerId,
            ]);
        }

        $this->logger->info('Organizer status updated', [
            'organizerId' => $updateOrganizerStatusDTO->organizerId,
            'status' => $updateOrganizerStatusDTO->status
        ]);

        return $this->organizerRepository->findFirstWhere([
            'id' => $updateOrganizerStatusDTO->organizerId,
            'account_id' => $updateOrganizerStatusDTO->accountId,
        ]);
    }
}
