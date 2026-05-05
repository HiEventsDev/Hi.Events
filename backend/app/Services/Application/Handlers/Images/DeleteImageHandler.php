<?php

namespace HiEvents\Services\Application\Handlers\Images;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Images\DTO\DeleteImageDTO;
use HiEvents\Services\Infrastructure\Image\ImageStorageService;

class DeleteImageHandler
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ImageStorageService $imageStorageService,
    ) {}

    /**
     * @throws CannotDeleteEntityException
     */
    public function handle(DeleteImageDTO $imageData): void
    {
        /** @var ImageDomainObject $image */
        $image = $this->imageRepository->findFirstWhere([
            'id' => $imageData->imageId,
            'account_id' => $imageData->accountId,
        ]);

        if ($image === null) {
            throw new CannotDeleteEntityException('You do not have permission to delete this image.');
        }

        $this->imageRepository->deleteWhere([
            'id' => $imageData->imageId,
            'account_id' => $imageData->accountId,
        ]);

        if ($image->getDisk() !== null && $image->getPath() !== null) {
            $this->imageStorageService->delete($image->getDisk(), $image->getPath());
        }
    }
}
