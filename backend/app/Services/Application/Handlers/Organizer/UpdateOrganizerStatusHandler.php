<?php

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\AccountNotVerifiedException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
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
        private readonly LoggerInterface              $logger,
        private readonly DatabaseManager              $databaseManager,
    )
    {
    }

    /**
     * @throws AccountNotVerifiedException|Throwable
     */
    public function handle(UpdateOrganizerStatusDTO $updateOrganizerStatusDTO): OrganizerDomainObject
    {
        return $this->databaseManager->transaction(function () use ($updateOrganizerStatusDTO) {
            return $this->updateOrganizerStatus($updateOrganizerStatusDTO);
        });
    }

    /**
     * @throws AccountNotVerifiedException
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

        $this->organizerRepository->updateWhere(
            attributes: ['status' => $updateOrganizerStatusDTO->status],
            where: ['id' => $updateOrganizerStatusDTO->organizerId]
        );

        $this->logger->info('Organizer status updated', [
            'organizerId' => $updateOrganizerStatusDTO->organizerId,
            'status' => $updateOrganizerStatusDTO->status
        ]);

        return $this->organizerRepository->findById($updateOrganizerStatusDTO->organizerId);
    }
}
