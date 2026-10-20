<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso;

use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;
use Illuminate\Support\Collection;

class GetSsoIdentitiesHandler
{
    public function __construct(
        private readonly UserIdentityRepositoryInterface $userIdentityRepository,
    ) {}

    /**
     * @return Collection<int, UserIdentityDomainObject>
     */
    public function handle(int $userId): Collection
    {
        return $this->userIdentityRepository->findWhere(
            where: ['user_id' => $userId],
            orderAndDirections: [new OrderAndDirection('created_at', 'asc')],
        );
    }
}
