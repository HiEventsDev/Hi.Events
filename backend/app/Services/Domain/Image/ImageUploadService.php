<?php

namespace HiEvents\Services\Domain\Image;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Infrastructure\Image\Exception\CouldNotUploadImageException;
use HiEvents\Services\Infrastructure\Image\ImageMetadataService;
use HiEvents\Services\Infrastructure\Image\ImageStorageService;
use Illuminate\Http\UploadedFile;

class ImageUploadService
{
    public function __construct(
        private readonly ImageStorageService      $imageStorageService,
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ImageMetadataService     $imageMetadataService,
    ) {
    }

    /**
     * @throws CouldNotUploadImageException
     */
    public function upload(
        UploadedFile $image,
        int          $entityId,
        string       $entityType,
        string       $imageType,
        int          $accountId,
    ): ImageDomainObject
    {
        $storedImage = $this->imageStorageService->store($image, $imageType);
        $metadata = $this->imageMetadataService->extractMetadata($image);

        $data = [
            'account_id' => $accountId,
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'type' => $imageType,
            'filename' => $storedImage->filename,
            'disk' => $storedImage->disk,
            'path' => $storedImage->path,
            'size' => $storedImage->size,
            'mime_type' => $storedImage->mime_type,
        ];

        if ($metadata !== null) {
            $data['width'] = $metadata->width;
            $data['height'] = $metadata->height;
            $data['avg_colour'] = $metadata->avg_colour;
            $data['lqip_base64'] = $metadata->lqip_base64;
        }

        return $this->imageRepository->create($data);
    }
}
