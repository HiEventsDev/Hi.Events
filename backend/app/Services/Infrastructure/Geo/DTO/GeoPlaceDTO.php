<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Geo\DTO;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DataTransferObjects\BaseDataObject;

class GeoPlaceDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $provider,
        public readonly string $provider_place_id,
        public readonly AddressDTO $address,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $display_name = null,
        public readonly ?array $raw_response = null,
    ) {}
}
