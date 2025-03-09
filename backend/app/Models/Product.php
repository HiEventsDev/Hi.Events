<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            ProductDomainObjectAbstract::SALES_VOLUME => 'float',
            ProductDomainObjectAbstract::SALES_TAX_VOLUME => 'float',
        ];
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'product_questions');
    }

    public function product_prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->orderBy('order');
    }

    public function tax_and_fees(): BelongsToMany
    {
        return $this->belongsToMany(TaxAndFee::class, 'product_taxes_and_fees');
    }

    public function capacity_assignments(): BelongsToMany
    {
        return $this->belongsToMany(CapacityAssignment::class, 'product_capacity_assignments');
    }

    public function check_in_lists(): BelongsToMany
    {
        return $this->belongsToMany(CheckInList::class, 'product_check_in_lists');
    }

    public function product_category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
