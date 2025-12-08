<?php

namespace HiEvents\Services\Infrastructure\Image;

use HiEvents\Services\Infrastructure\Image\DTO\ImageMetadataDTO;
use Illuminate\Http\UploadedFile;
use Imagick;
use Psr\Log\LoggerInterface;

class ImageMetadataService
{
    private const LQIP_MAX_DIMENSION = 16;
    private const LQIP_QUALITY = 60;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function extractMetadata(UploadedFile $image): ?ImageMetadataDTO
    {
        if (!$this->isImagickAvailable()) {
            return null;
        }

        try {
            $imagick = new Imagick($image->getRealPath());

            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            $avgColour = $this->extractAverageColour($imagick);
            $lqipBase64 = $this->generateLqip($imagick);

            $imagick->clear();
            $imagick->destroy();

            return new ImageMetadataDTO(
                width: $width,
                height: $height,
                avg_colour: $avgColour,
                lqip_base64: $lqipBase64,
            );
        } catch (\Exception $e) {
            $this->logger->warning('Failed to extract image metadata: ' . $e->getMessage());

            return null;
        }
    }

    private function isImagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists(Imagick::class);
    }

    private function extractAverageColour(Imagick $imagick): string
    {
        $clone = clone $imagick;
        $clone->resizeImage(1, 1, Imagick::FILTER_LANCZOS, 1);
        $pixel = $clone->getImagePixelColor(0, 0);
        $rgb = $pixel->getColor();
        $clone->clear();
        $clone->destroy();

        return sprintf('#%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    private function generateLqip(Imagick $imagick): string
    {
        $clone = clone $imagick;

        $width = $clone->getImageWidth();
        $height = $clone->getImageHeight();

        if ($width > $height) {
            $newWidth = self::LQIP_MAX_DIMENSION;
            $newHeight = (int) round($height * (self::LQIP_MAX_DIMENSION / $width));
        } else {
            $newHeight = self::LQIP_MAX_DIMENSION;
            $newWidth = (int) round($width * (self::LQIP_MAX_DIMENSION / $height));
        }

        $newWidth = max(1, $newWidth);
        $newHeight = max(1, $newHeight);

        $clone->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
        $clone->setImageFormat('webp');
        $clone->setImageCompressionQuality(self::LQIP_QUALITY);
        $clone->stripImage();

        $blob = $clone->getImageBlob();
        $clone->clear();
        $clone->destroy();

        return 'data:image/webp;base64,' . base64_encode($blob);
    }
}
