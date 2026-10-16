<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Widget extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
