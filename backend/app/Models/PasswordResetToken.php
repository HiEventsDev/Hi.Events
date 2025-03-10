<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class PasswordResetToken extends BaseModel
{
    use SoftDeletes;
}
