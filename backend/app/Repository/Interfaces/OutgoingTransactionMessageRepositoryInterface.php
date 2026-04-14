<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OutgoingTransactionMessageDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<OutgoingTransactionMessageDomainObject>
 */
interface OutgoingTransactionMessageRepositoryInterface extends RepositoryInterface
{
    public function findBySesMessageId(string $sesMessageId): ?OutgoingTransactionMessageDomainObject;

    public function markAsBounced(int $id): void;

    public function markAsDelivered(int $id): void;

    public function getForEvent(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function getFailuresForEvent(int $eventId, int $perPage = 20, bool $showResolved = false): LengthAwarePaginator;

    public function findAccountIdByRecipientEmail(string $email): ?int;
}
