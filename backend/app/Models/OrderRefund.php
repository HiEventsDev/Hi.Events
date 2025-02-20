<?php

namespace HiEvents\Models;

class OrderRefund extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }
}
