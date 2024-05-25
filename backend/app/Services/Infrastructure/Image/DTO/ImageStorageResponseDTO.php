<?php

namespace HiEvents\Services\Infrastructure\Image\DTO;

readonly class ImageStorageResponseDTO
{
    public function __construct(
        public string $filename,
        public string $disk,
        public string $path,
        public int    $size,
        public string $mime_type,
    )
    {
    }
}
