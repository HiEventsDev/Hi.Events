<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class TicketLookupToken extends BaseModel
{
    use SoftDeletes;

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
