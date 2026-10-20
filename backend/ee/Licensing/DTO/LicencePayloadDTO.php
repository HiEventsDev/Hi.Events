<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\DTO;

use Carbon\CarbonImmutable;
use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\Plan;

class LicencePayloadDTO extends BaseDataObject
{
    /**
     * @param  LicensedFeature[]  $features
     */
    public function __construct(
        public readonly string $lid,
        public readonly string $customer,
        public readonly Plan $plan,
        public readonly array $features,
        public readonly CarbonImmutable $issued_at,
        public readonly CarbonImmutable $expires_at,
    ) {}
}
