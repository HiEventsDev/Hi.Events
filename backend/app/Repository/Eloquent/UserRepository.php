<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountUserDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Models\AccountUser;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

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

    public function findByIdAndAccountId(int $userId, int $accountId): UserDomainObject
    {
        $accountUser = AccountUser::where('user_id', $userId)->where('account_id', $accountId)->first();

        if (!$accountUser) {
            throw new ResourceNotFoundException(__('User not found in this account'));
        }

        $accountUser = $this->handleSingleResult($accountUser, AccountUserDomainObject::class);

        try {
            /** @var UserDomainObject $user */
            $user = $this->handleSingleResult($this->model->findOrFail($userId));
        } catch (ModelNotFoundException) {
            throw new ResourceNotFoundException(__('User not found'));
        }

        $user->setCurrentAccountUser($accountUser);

        return $user;
    }

    public function findUsersByAccountId(int $accountId): ?Collection
    {
        $users = $this->model->whereHas('accounts', function ($query) use ($accountId) {
            $query->where('account_id', $accountId);
        })->get();

        $users = $this->handleResults($users);

        return $users->sortByDesc(fn(UserDomainObject $user) => $user->getUpdatedAt());
    }
}
