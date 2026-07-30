<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Resources\Account\AccountResource;
use HiEvents\Services\Domain\Account\AccountDeletionService;
use Illuminate\Http\JsonResponse;

class GetAccountAction extends BaseAction
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly AccountDeletionService $accountDeletionService,
    ) {}

    public function __invoke(?int $accountId = null): JsonResponse
    {
        $this->minimumAllowedRole(Role::ORGANIZER);

        $authenticatedAccountId = $this->getAuthenticatedAccountId();

        $account = $this->accountRepository->findById($authenticatedAccountId);
        $account->setActiveDeletionRequest($this->accountDeletionService->findActiveRequest($authenticatedAccountId));

        return $this->resourceResponse(AccountResource::class, $account);
    }
}
