<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Eloquent;

use Illuminate\Support\Collection;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\DomainObjects\UserDomainObject;
use TicketKitten\Models\User;
use TicketKitten\Repository\Interfaces\UserRepositoryInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function getModel(): string
    {
        return User::class;
    }

    public function getDomainObject(): string
    {
        return UserDomainObject::class;
    }

    public function findUsersByAccountId(int $accountId): ?Collection
    {
        $users = $this->findWhere([
            'account_id' => $accountId,
        ]);

        return $users->sortByDesc(function (UserDomainObject $user) {
            return $user->getUpdatedAt();
        })->sortByDesc(function ($user) {
            return $user->getIsAccountOwner();
        })->sortByDesc(function (UserDomainObject $user) {
            return $user->getStatus() === Role::ADMIN->name;
        })->sortByDesc(function (UserDomainObject $user) {
            return $user->getStatus() === Role::ORGANIZER->name;
        });
    }
}
