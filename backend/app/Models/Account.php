<?php

declare(strict_types=1);

namespace HiEvents\Models;

use HiEvents\DomainObjects\Enums\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends BaseModel
{
    use SoftDeletes;

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

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(
            related: AccountConfiguration::class,
            foreignKey: 'account_configuration_id',
        );
    }

    protected function getCastMap(): array
    {
        return [
            'stripe_connect_setup_complete' => 'boolean',
            'is_manually_verified' => 'boolean',
        ];
    }
}
