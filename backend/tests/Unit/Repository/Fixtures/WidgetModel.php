<?php

declare(strict_types=1);

namespace Tests\Unit\Repository\Fixtures;

use HiEvents\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WidgetModel extends BaseModel
{
    use SoftDeletes;

    protected $table = 'br_test_widgets';

    protected function getFillableFields(): array
    {
        return [
            'category_id',
            'name',
            'sku',
            'quantity',
            'price',
            'is_active',
            'description',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'is_active' => 'boolean',
            'quantity' => 'integer',
            'price' => 'float',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WidgetCategoryModel::class, 'category_id');
    }
}
