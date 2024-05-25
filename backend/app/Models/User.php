<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use RuntimeException;

class User extends BaseModel implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract, JWTSubject
{
    use Notifiable;
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use MustVerifyEmail;

    /** @var array */
    protected $guarded = [];

    protected static ?int $currentAccountId;

    public static function setCurrentAccountId($accountId): void
    {
        self::$currentAccountId = $accountId;
    }

    public static function getCurrentAccountId(): ?int
    {
        if (self::$currentAccountId === null) {
            throw new RuntimeException(__('Current account ID is not set'));
        }

        return self::$currentAccountId;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [

        ];
    }

    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function currentAccount(): HasOneThrough
    {
        return $this->hasOneThrough(
            related: Account::class,
            through: AccountUser::class,
            firstKey: 'user_id',
            secondKey: 'id',
            localKey: 'id',
            secondLocalKey: 'account_id'
        )
            ->where('account_id', static::getCurrentAccountId());
    }

    public function currentAccountUser(): HasOne
    {
        return $this->hasOne(AccountUser::class)
            ->where('account_id', static::getCurrentAccountId());
    }
}
