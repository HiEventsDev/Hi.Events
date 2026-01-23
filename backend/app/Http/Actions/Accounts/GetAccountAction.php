<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Resources\Account\AccountResource;
use Illuminate\Http\JsonResponse;

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

        $account = $this->accountRepository
            ->loadRelation(new Relationship(
                domainObject: AccountConfigurationDomainObject::class,
                name: 'configuration',
            ))
            ->loadRelation(AccountStripePlatformDomainObject::class)
            ->findById($this->getAuthenticatedAccountId());

        return $this->resourceResponse(AccountResource::class, $account);
    }
}
