<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountConfiguration extends BaseModel
{
    protected $table = 'account_configuration';

    protected function getCastMap(): array
    {
        return [
            'application_fees' => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function account(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
