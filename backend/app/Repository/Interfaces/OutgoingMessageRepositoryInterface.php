<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\OutgoingMessageDomainObject;

/**
 * @extends RepositoryInterface<OutgoingMessageDomainObject>
 */
interface OutgoingMessageRepositoryInterface extends RepositoryInterface
{
    public function findAccountIdByRecipientEmail(string $email): ?int;

    public function markAsBounced(string $sesMessageId): bool;
}
