<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing;

enum Plan: string
{
    case ENTERPRISE = 'enterprise';
    case CLOUD = 'cloud';

    /**
     * @return LicensedFeature[]
     */
    public function features(): array
    {
        return match ($this) {
            self::ENTERPRISE, self::CLOUD => [
                LicensedFeature::SEATING,
                LicensedFeature::BOX_OFFICE,
            ],
        };
    }
}
