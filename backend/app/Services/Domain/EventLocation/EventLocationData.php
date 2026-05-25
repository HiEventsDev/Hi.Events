<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventLocation;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\LocationType;

class EventLocationData extends BaseDataObject
{
    public function __construct(
        public readonly LocationType $type,
        public readonly ?int $location_id = null,
        public readonly ?string $online_event_connection_details = null,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            type: LocationType::fromName($payload['type']),
            location_id: $payload['location_id'] ?? null,
            online_event_connection_details: $payload['online_event_connection_details'] ?? null,
        );
    }
}
