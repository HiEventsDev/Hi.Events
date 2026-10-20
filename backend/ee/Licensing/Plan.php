<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing;

enum Plan: string
{
    case OWN_EVENTS = 'own_events';
    case PLATFORM = 'platform';
    case CLOUD = 'cloud';

    /**
     * @return LicensedFeature[]
     */
    public function features(): array
    {
        return match ($this) {
            self::OWN_EVENTS => [
                LicensedFeature::WHITE_LABEL,
            ],
            self::PLATFORM => [
                LicensedFeature::WHITE_LABEL,
                LicensedFeature::SEATING,
                LicensedFeature::BOX_OFFICE,
                LicensedFeature::ENTERPRISE_SSO,
            ],
            self::CLOUD => [
                LicensedFeature::SEATING,
                LicensedFeature::BOX_OFFICE,
                LicensedFeature::ENTERPRISE_SSO,
            ],
        };
    }
}
