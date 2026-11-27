<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class CrmSignupForm extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'tag_ids' => 'array',
        ];
    }
}
