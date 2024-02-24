<?php

namespace HiEvents\Http\DataTransferObjects;

use Illuminate\Http\UploadedFile;
use HiEvents\DomainObjects\Enums\EventImageType;
use HiEvents\DataTransferObjects\BaseDTO;

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
