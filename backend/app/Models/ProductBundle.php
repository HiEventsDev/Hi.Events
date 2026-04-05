<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\ProductBundleDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBundle extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            ProductBundleDomainObjectAbstract::PRICE => 'float',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            ProductBundleDomainObjectAbstract::EVENT_ID,
            ProductBundleDomainObjectAbstract::NAME,
            ProductBundleDomainObjectAbstract::DESCRIPTION,
            ProductBundleDomainObjectAbstract::PRICE,
            ProductBundleDomainObjectAbstract::CURRENCY,
            ProductBundleDomainObjectAbstract::MAX_PER_ORDER,
            ProductBundleDomainObjectAbstract::QUANTITY_AVAILABLE,
            ProductBundleDomainObjectAbstract::SALE_START_DATE,
            ProductBundleDomainObjectAbstract::SALE_END_DATE,
            ProductBundleDomainObjectAbstract::IS_ACTIVE,
            ProductBundleDomainObjectAbstract::SORT_ORDER,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class);
    }
}
