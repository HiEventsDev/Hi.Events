<?php

namespace HiEvents\Services\Application\Handlers\Images\DTO;

use Illuminate\Http\UploadedFile;

class CreateImageDTO
{
    public function __construct(
        public readonly int          $userId,
        public readonly UploadedFile $image,
    )
    {
    }
}
