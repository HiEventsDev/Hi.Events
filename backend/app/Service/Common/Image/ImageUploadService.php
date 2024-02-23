<?php

namespace TicketKitten\Service\Common\Image;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use TicketKitten\DomainObjects\ImageDomainObject;
use TicketKitten\Repository\Interfaces\ImageRepositoryInterface;

readonly class ImageUploadService
{
    public function __construct(
        private FilesystemManager        $filesystemManager,
        private ImageRepositoryInterface $imageRepository,
        private Repository               $config
    )
    {
    }

    public function upload(
        UploadedFile $image,
        int          $entityId,
        string       $entityType,
        string       $imageType,
    ): ImageDomainObject
    {
        $filename = Str::slug($image->getClientOriginalName()) . '-' . Str::random(5) . '.' . $image->getClientOriginalExtension();
        $size = $image->getSize();
        $mimeType = $image->getClientMimeType();
        $disk = $this->config->get('filesystems.public');

        $path = $this->filesystemManager->disk($disk)->putFileAs(
            path: strtolower($imageType),
            file: $image,
            name: $filename
        );

        if ($path === false) {
            throw new RuntimeException(__('Could not upload image'));
        }

        return $this->imageRepository->create([
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'type' => $imageType,
            'filename' => $image->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'size' => $size,
            'mime_type' => $mimeType
        ]);
    }
}
