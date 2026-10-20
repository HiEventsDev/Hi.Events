<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Location\DTO;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertLocationDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly int $account_id,
        public readonly ?string $name,
        public readonly AddressDTO $structured_address,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $provider = null,
        public readonly ?string $provider_place_id = null,
    ) {}
}
