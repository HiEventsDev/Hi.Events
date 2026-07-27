<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Geo\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class GeoSuggestionDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $provider_place_id,
        public readonly string $primary_text,
        public readonly ?string $secondary_text = null,
    ) {}
}
