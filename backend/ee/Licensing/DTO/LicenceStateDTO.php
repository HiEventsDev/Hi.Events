<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing\DTO;

use Carbon\CarbonImmutable;
use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Enterprise\Licensing\LicenceStatus;
use HiEvents\Enterprise\Licensing\LicensedFeature;

class LicenceStateDTO extends BaseDataObject
{
    /**
     * @param  LicensedFeature[]  $features
     */
    public function __construct(
        public readonly LicenceStatus $status,
        public readonly array $features,
        public readonly ?LicencePayloadDTO $licence = null,
        public readonly ?CarbonImmutable $grace_ends_at = null,
        public readonly ?string $invalid_reason = null,
    ) {}
}
