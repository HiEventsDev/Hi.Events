<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\ProductQuestionDomainObjectAbstract;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductQuestion extends BaseModel
{
    use SoftDeletes;

    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    protected function getFillableFields(): array
    {
        return [
            ProductQuestionDomainObjectAbstract::QUESTION_ID,
            ProductQuestionDomainObjectAbstract::PRODUCT_ID,
        ];
    }
}
