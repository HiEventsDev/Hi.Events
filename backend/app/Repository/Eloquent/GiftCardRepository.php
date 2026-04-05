<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\GiftCardDomainObject;
use HiEvents\Models\GiftCard;
use HiEvents\Repository\Interfaces\GiftCardRepositoryInterface;
use Illuminate\Support\Collection;

class GiftCardRepository extends BaseRepository implements GiftCardRepositoryInterface
{
    protected function getModel(): string
    {
        return GiftCard::class;
    }

    public function getDomainObject(): string
    {
        return GiftCardDomainObject::class;
    }

    public function findByCode(string $code): ?GiftCardDomainObject
    {
        $model = $this->model->where('code', $code)->first();

        if ($model === null) {
            return null;
        }

        return $this->handleSingleResult($model);
    }

    public function findByAccountId(int $accountId): Collection
    {
        return $this->handleResults(
            $this->model->where('account_id', $accountId)
                ->orderBy('created_at', 'desc')
                ->paginate()
        );
    }
}
