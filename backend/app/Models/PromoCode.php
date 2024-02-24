<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;

class PromoCode extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            PromoCodeDomainObjectAbstract::DISCOUNT => 'float',
            PromoCodeDomainObjectAbstract::EXPIRY_DATE => 'datetime',
            PromoCodeDomainObjectAbstract::APPLICABLE_TICKET_IDS => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            PromoCodeDomainObjectAbstract::CODE,
            PromoCodeDomainObjectAbstract::DISCOUNT,
            PromoCodeDomainObjectAbstract::DISCOUNT_TYPE,
            PromoCodeDomainObjectAbstract::APPLICABLE_TICKET_IDS,
            PromoCodeDomainObjectAbstract::EXPIRY_DATE,
            PromoCodeDomainObjectAbstract::EVENT_ID,
            PromoCodeDomainObjectAbstract::MAX_ALLOWED_USAGES,
        ];
    }
}
