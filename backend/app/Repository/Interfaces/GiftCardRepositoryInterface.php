<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\GiftCardDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<GiftCardDomainObject>
 */
interface GiftCardRepositoryInterface extends RepositoryInterface
{
    public function findByCode(string $code): ?GiftCardDomainObject;

    public function findByAccountId(int $accountId): Collection;
}
