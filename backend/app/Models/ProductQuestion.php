<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\ProductQuestionDomainObjectAbstract;

class ProductQuestion extends BaseModel
{
    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [
            ProductQuestionDomainObjectAbstract::QUESTION_ID,
            ProductQuestionDomainObjectAbstract::PRODUCT_ID,
        ];
    }
}
