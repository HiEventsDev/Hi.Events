<?php

namespace HiEvents\DomainObjects\Enums;

use HiEvents\Enterprise\Licensing\LicensedFeature;

enum FeatureFlag: string
{
    use BaseEnum;

    case SEATING = 'seating';
    case BOX_OFFICE = 'box_office';
    case ENTERPRISE_SSO = 'enterprise_sso';

    public function licensedFeature(): LicensedFeature
    {
        return match ($this) {
            self::SEATING => LicensedFeature::SEATING,
            self::BOX_OFFICE => LicensedFeature::BOX_OFFICE,
            self::ENTERPRISE_SSO => LicensedFeature::ENTERPRISE_SSO,
        };
    }
}
