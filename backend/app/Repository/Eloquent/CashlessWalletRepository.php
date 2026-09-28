<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CashlessWalletDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\CashlessWallet;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<CashlessWalletDomainObject>
 */
class CashlessWalletRepository extends BaseRepository implements CashlessWalletRepositoryInterface
{
    protected function getModel(): string
    {
        return CashlessWallet::class;
    }

    public function getDomainObject(): string
    {
        return CashlessWalletDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [CashlessWalletDomainObjectAbstract::EVENT_ID, '=', $eventId],
        ];

        if (! empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder->whereHas('attendee', static function (Builder $attendeeBuilder) use ($params) {
                    $attendeeBuilder
                        ->where(AttendeeDomainObjectAbstract::FIRST_NAME, 'ilike', '%'.$params->query.'%')
                        ->orWhere(AttendeeDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$params->query.'%')
                        ->orWhere(AttendeeDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%')
                        ->orWhere(AttendeeDomainObjectAbstract::PUBLIC_ID, 'ilike', '%'.$params->query.'%');
                });
            };
        }

        $this->model = $this->model->orderBy(
            column: $this->validateSortColumn($params->sort_by, CashlessWalletDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, CashlessWalletDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function lockById(int $walletId): ?CashlessWalletDomainObject
    {
        return $this->runQuery(fn () => $this->handleSingleResult(
            $this->model->where('id', $walletId)->lockForUpdate()->first()
        ));
    }
}
