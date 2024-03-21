<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Enums\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Account extends BaseModel
{
    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function usersByRole(Role $roleName): BelongsToMany
    {
        return $this->users()->wherePivot('role', '=', $roleName->name);
    }
}
