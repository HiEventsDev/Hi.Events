<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use Illuminate\Support\Collection;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;

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
