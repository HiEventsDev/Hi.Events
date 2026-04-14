<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OutgoingMessageDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<OutgoingMessageDomainObject>
 */
interface OutgoingMessageRepositoryInterface extends RepositoryInterface
{
    public function findAccountIdByRecipientEmail(string $email): ?int;

    public function markAsBounced(string $sesMessageId): bool;

    public function markAsDelivered(string $sesMessageId): bool;

    public function getForEvent(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}
