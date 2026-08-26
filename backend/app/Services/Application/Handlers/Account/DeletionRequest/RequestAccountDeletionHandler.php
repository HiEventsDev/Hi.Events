<?php

namespace HiEvents\Services\Application\Handlers\Account\DeletionRequest;

use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\DeletionRequest\DTO\RequestAccountDeletionDTO;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use Illuminate\Validation\ValidationException;
use Throwable;

class RequestAccountDeletionHandler
{
    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
        private readonly AccountRepositoryInterface $accountRepository,
    ) {}

    /**
     * @throws CannotDeleteEntityException
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(RequestAccountDeletionDTO $dto): AccountDeletionRequestDomainObject
    {
        $account = $this->accountRepository->findById($dto->accountId);

        if (mb_strtolower(trim($dto->confirmation)) !== mb_strtolower(trim($account->getName()))) {
            throw ValidationException::withMessages([
                'confirmation' => __('The confirmation does not match your account name.'),
            ]);
        }

        return $this->accountDeletionService->requestDeletion(
            accountId: $dto->accountId,
            requestedByUserId: $dto->requestedByUserId,
            initiator: $dto->initiatedBy,
            reason: $dto->reason,
        );
    }
}
