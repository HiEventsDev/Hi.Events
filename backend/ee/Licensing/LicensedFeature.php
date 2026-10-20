<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing;

enum LicensedFeature: string
{
    case SEATING = 'seating';
    case BOX_OFFICE = 'box_office';
    case ENTERPRISE_SSO = 'enterprise_sso';
    case WHITE_LABEL = 'white_label';
}
