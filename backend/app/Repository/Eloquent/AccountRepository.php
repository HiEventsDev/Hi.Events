<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Models\Account;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountRepository extends BaseRepository implements AccountRepositoryInterface
{
    protected function getModel(): string
    {
        return Account::class;
    }

    public function getDomainObject(): string
    {
        return AccountDomainObject::class;
    }

    public function findByEventId(int $eventId): AccountDomainObject
    {
        $account = $this
            ->model
            ->select('accounts.*')
            ->join('events', 'accounts.id', '=', 'events.account_id')
            ->where('events.id', $eventId)
            ->first();

        return $this->handleSingleResult($account, AccountDomainObject::class);
    }

    public function getAllAccountsWithCounts(?string $search, int $perPage): LengthAwarePaginator
    {
        $query = $this->model
            ->select('accounts.*')
            ->withCount(['events', 'users'])
            ->with([
                'users' => function ($query) {
                    $query->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                        ->withPivot('role');
                },
                'messagingTier',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('accounts.name', 'like', "{$search}%")
                    ->orWhere('accounts.email', 'like', "{$search}%")
                    ->orWhereHas('users', function ($userQuery) use ($search) {
                        $userQuery->where('users.email', 'like', "{$search}%");
                    });
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getAccountWithDetails(int $accountId): Account
    {
        return $this->model
            ->withCount(['events', 'users'])
            ->with([
                'configuration',
                'account_vat_setting',
                'messagingTier',
                'users' => function ($query) {
                    $query->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                        ->withPivot('role');
                }
            ])
            ->findOrFail($accountId);
    }
}
