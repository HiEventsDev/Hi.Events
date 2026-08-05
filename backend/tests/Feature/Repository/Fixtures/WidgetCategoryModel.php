<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Fixtures;

use HiEvents\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetCategoryModel extends BaseModel
{
    protected $table = 'br_test_widget_categories';

    protected function getFillableFields(): array
    {
        return ['name'];
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(WidgetModel::class, 'category_id');
    }
}
