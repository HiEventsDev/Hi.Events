<?php

namespace HiEvents\DomainObjects\Enums;

enum OfflinePaymentAvailability
{
    use BaseEnum;

    case EVERYONE;
    case PROMO_CODE_ONLY;
}
