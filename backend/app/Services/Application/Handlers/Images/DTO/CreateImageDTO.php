<?php

namespace HiEvents\Services\Application\Handlers\Images\DTO;

use HiEvents\DomainObjects\Enums\ImageType;
use Illuminate\Http\UploadedFile;

class CreateImageDTO
{
    public function __construct(
        public readonly int          $userId,
        public readonly int          $accountId,
        public readonly UploadedFile $image,
        public readonly ?ImageType   $imageType = null,
        public readonly ?int         $entityId = null,
    )
    {
    }

    public function isGeneric(): bool
    {
        return $this->imageType === null && $this->entityId === null;
    }
}
