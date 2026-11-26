<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Models\TicketTransfer;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;

/**
 * @extends BaseRepository<TicketTransferDomainObject>
 */
class TicketTransferRepository extends BaseRepository implements TicketTransferRepositoryInterface
{
    protected function getModel(): string
    {
        return TicketTransfer::class;
    }

    public function getDomainObject(): string
    {
        return TicketTransferDomainObject::class;
    }

    public function findByTokenHashLocked(string $tokenHash): ?TicketTransferDomainObject
    {
        return $this->runQuery(function () use ($tokenHash) {
            $model = TicketTransfer::query()->where('token_hash', $tokenHash)->lockForUpdate()->first();

            return $model === null ? null : $this->handleSingleResult($model);
        });
    }
}
