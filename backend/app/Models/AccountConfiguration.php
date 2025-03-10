<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountConfiguration extends BaseModel
{
    use SoftDeletes;

    protected $table = 'account_configuration';

    protected function getCastMap(): array
    {
        return [
            'application_fees' => 'array',
        ];
    }

    public function account(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
