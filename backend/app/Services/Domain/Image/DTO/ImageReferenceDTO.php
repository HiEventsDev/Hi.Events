<?php

namespace HiEvents\Services\Domain\Image\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ImageReferenceDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $entity_type,
        public readonly int $entity_id,
        public readonly string $entity_name,
        public readonly string $field_label,
        public readonly bool $is_protected,
        public readonly ?string $event_status = null,
        public readonly ?string $event_lifecycle_status = null,
    ) {}
}
