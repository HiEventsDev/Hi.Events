<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Consent;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class BounceReactivationPolicy
{
    private const PROVIDER_REJECTION_BLOCK_DAYS = 30;

    public static function availableAt(?string $providerRejectedAt): ?CarbonInterface
    {
        if ($providerRejectedAt === null) {
            return null;
        }

        $availableAt = Carbon::parse($providerRejectedAt)->addDays(self::PROVIDER_REJECTION_BLOCK_DAYS);

        return $availableAt->isFuture() ? $availableAt : null;
    }
}
