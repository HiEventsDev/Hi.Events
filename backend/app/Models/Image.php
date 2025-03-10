<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends BaseModel
{
    use SoftDeletes;

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
