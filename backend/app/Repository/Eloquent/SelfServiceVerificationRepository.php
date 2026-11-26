<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\SelfServiceVerificationDomainObject;
use HiEvents\Models\SelfServiceVerification;
use HiEvents\Repository\Interfaces\SelfServiceVerificationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<SelfServiceVerificationDomainObject>
 */
class SelfServiceVerificationRepository extends BaseRepository implements SelfServiceVerificationRepositoryInterface
{
    protected function getModel(): string
    {
        return SelfServiceVerification::class;
    }

    public function getDomainObject(): string
    {
        return SelfServiceVerificationDomainObject::class;
    }

    public function deleteExpiredBefore(string $expiredBefore): int
    {
        return $this->runQuery(fn () => SelfServiceVerification::query()
            ->where('created_at', '<', $expiredBefore)
            ->where(fn (Builder $query) => $query->whereNull('code_expires_at')->orWhere('code_expires_at', '<', $expiredBefore))
            ->where(fn (Builder $query) => $query->whereNull('session_expires_at')->orWhere('session_expires_at', '<', $expiredBefore))
            ->delete());
    }
}
