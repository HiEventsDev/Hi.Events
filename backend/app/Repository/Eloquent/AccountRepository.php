<?php

declare(strict_types=1);

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\AccountDomainObject;
use TicketKitten\Models\Account;
use TicketKitten\Repository\Interfaces\AccountRepositoryInterface;

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
            ->join('events', 'accounts.id', '=', 'events.account_id')
            ->where('events.id', $eventId)
            ->first();

        return $this->handleSingleResult($account, AccountDomainObject::class);
    }
}
