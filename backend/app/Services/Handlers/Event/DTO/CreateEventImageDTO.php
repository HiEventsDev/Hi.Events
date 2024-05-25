<?php

namespace HiEvents\Services\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\EventImageType;
use Illuminate\Http\UploadedFile;

class CreateEventImageDTO extends BaseDTO
{
    public function __construct(
        public readonly int            $event_id,
        public readonly UploadedFile   $image,
        public readonly EventImageType $type,
    )
    {
    }
}
