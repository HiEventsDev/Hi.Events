<?php

namespace HiEvents\Services\Application\Handlers\Images;

use HiEvents\DomainObjects\Enums\ImageTypes;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Services\Application\Handlers\Images\DTO\CreateImageDTO;
use HiEvents\Services\Domain\Image\ImageUploadService;
use HiEvents\Services\Infrastructure\Image\Exception\CouldNotUploadImageException;

class CreateImageHandler
{
    public function __construct(
        private readonly ImageUploadService $imageUploadService,
    )
    {
    }

    /**
     * @throws CouldNotUploadImageException
     */
    public function handle(CreateImageDTO $imageData): ImageDomainObject
    {
        // For generic images, we associate them with the user
        return $this->imageUploadService->upload(
            image: $imageData->image,
            entityId: $imageData->userId,
            entityType: UserDomainObject::class,
            imageType: ImageTypes::GENERIC->name,
        );
    }
}
