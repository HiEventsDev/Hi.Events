<?php

namespace HiEvents\Services\Infrastructure\Image\DTO;

readonly class ImageMetadataDTO
{
    public function __construct(
        public int     $width,
        public int     $height,
        public string  $avg_colour,
        public string  $lqip_base64,
    ) {
    }
}
