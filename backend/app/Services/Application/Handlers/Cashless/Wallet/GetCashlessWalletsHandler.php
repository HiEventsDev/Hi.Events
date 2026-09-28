<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Wallet;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCashlessWalletsHandler
{
    public function __construct(
        private readonly CashlessWalletRepositoryInterface $walletRepository,
    ) {}

    public function handle(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        return $this->walletRepository
            ->loadRelation(new Relationship(AttendeeDomainObject::class, name: 'attendee'))
            ->findByEventId($eventId, $params);
    }
}
