<?php

declare(strict_types=1);

namespace TicketKitten\Http\Actions\Accounts;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\AccountRepositoryInterface;
use TicketKitten\Resources\Account\AccountResource;

class GetAccountAction extends BaseAction
{
    protected AccountRepositoryInterface $accountRepository;

    public function __construct(AccountRepositoryInterface $accountRepository)
    {
        $this->accountRepository = $accountRepository;
    }

    public function __invoke(?int $accountId = null): JsonResponse
    {
        $this->minimumAllowedRole(Role::ORGANIZER);

        $account = $this->accountRepository->findById($this->getAuthenticatedUser()->getAccountId());

        return $this->resourceResponse(AccountResource::class, $account);
    }
}
