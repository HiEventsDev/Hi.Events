<?php

namespace HiEvents\Services\Infrastructure\Image;

use HiEvents\Services\Infrastructure\Image\DTO\ImageStorageResponseDTO;
use HiEvents\Services\Infrastructure\Image\Exception\CouldNotUploadImageException;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

readonly class ImageStorageService
{
    public function __construct(
        private FilesystemManager $filesystemManager,
        private Repository        $config,
        private LoggerInterface   $logger,
    )
    {
    }

    /**
     * @throws CouldNotUploadImageException
     */
    public function store(UploadedFile $image, string $imageType): ImageStorageResponseDTO
    {
        $filename = Str::slug($image->getClientOriginalName()) . '-' . Str::random(5) . '.' . $image->getClientOriginalExtension();
        $disk = $this->config->get('filesystems.public');

        $path = $this->filesystemManager->disk($disk)->putFileAs(
            path: strtolower($imageType),
            file: $image,
            name: $filename
        );

        if ($path === false) {
            $this->logger->error(__('Could not upload image to :disk. Check :disk is configured correctly', ['disk' => $disk,]), [
                    'filename' => $filename,
                    'original_filename' => $image->getClientOriginalName()
                ]
            );

            throw new CouldNotUploadImageException(__('Could not upload image'));
        }

        return new ImageStorageResponseDTO  (
            filename: $filename,
            disk: $disk,
            path: $path,
            size: $image->getSize(),
            mime_type: $image->getMimeType()
        );
    }
}
