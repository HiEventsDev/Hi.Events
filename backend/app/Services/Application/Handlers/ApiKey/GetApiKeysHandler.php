<?php

namespace HiEvents\Services\Application\Handlers\ApiKey;

use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\PersonalAccessTokenRepositoryInterface;
use Illuminate\Support\Collection;

class GetApiKeysHandler
{
    public function __construct(
        private readonly PersonalAccessTokenRepositoryInterface $apiKeyRepository,
    ) {}

    /**
     * @return Collection<int, PersonalAccessTokenDomainObject>
     */
    public function handle(int $accountId, int $userId, bool $includeTeamKeys): Collection
    {
        $where = [
            PersonalAccessTokenDomainObject::ACCOUNT_ID => $accountId,
        ];

        if (! $includeTeamKeys) {
            $where[PersonalAccessTokenDomainObject::TOKENABLE_ID] = $userId;
        }

        return $this->apiKeyRepository
            ->loadRelation(new Relationship(UserDomainObject::class, name: 'user'))
            ->findWhere(
                where: $where,
                orderAndDirections: [
                    new OrderAndDirection(PersonalAccessTokenDomainObject::CREATED_AT, OrderAndDirection::DIRECTION_DESC),
                    new OrderAndDirection(PersonalAccessTokenDomainObject::ID, OrderAndDirection::DIRECTION_DESC),
                ],
            );
    }
}
