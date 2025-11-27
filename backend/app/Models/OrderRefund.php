<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRefund extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'metadata' => 'array',
            'amount' => 'float',
        ];
    }

}
