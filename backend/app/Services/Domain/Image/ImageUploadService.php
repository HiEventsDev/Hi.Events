<?php

namespace HiEvents\Services\Domain\Image;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Infrastructure\Image\Exception\CouldNotUploadImageException;
use HiEvents\Services\Infrastructure\Image\ImageStorageService;
use Illuminate\Http\UploadedFile;

readonly class ImageUploadService
{
    public function __construct(
        private ImageStorageService      $imageStorageService,
        private ImageRepositoryInterface $imageRepository
    )
    {
    }

    /**
     * @throws CouldNotUploadImageException
     */
    public function upload(
        UploadedFile $image,
        int          $entityId,
        string       $entityType,
        string       $imageType
    ): ImageDomainObject
    {
        $storedImage = $this->imageStorageService->store($image, $imageType);

        return $this->imageRepository->create([
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'type' => $imageType,
            'filename' => $storedImage->filename,
            'disk' => $storedImage->disk,
            'path' => $storedImage->path,
            'size' => $storedImage->size,
            'mime_type' => $storedImage->mime_type,
        ]);
    }
}
