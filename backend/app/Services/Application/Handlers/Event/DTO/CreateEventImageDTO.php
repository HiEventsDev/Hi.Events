<?php

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\ImageType;
use Illuminate\Http\UploadedFile;

class CreateEventImageDTO extends BaseDTO
{
    public function __construct(
        public readonly int          $eventId,
        public readonly int          $accountId,
        public readonly UploadedFile $image,
        public readonly ImageType    $imageType,
    )
    {
    }
}
