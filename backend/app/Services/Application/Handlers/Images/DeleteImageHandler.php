<?php

namespace HiEvents\Services\Application\Handlers\Images;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Exceptions\ImageInUseException;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Application\Handlers\Images\DTO\DeleteImageDTO;
use HiEvents\Services\Domain\Image\ImageInUseService;
use HiEvents\Services\Infrastructure\Image\ImageStorageService;

class DeleteImageHandler
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly ImageStorageService $imageStorageService,
        private readonly ImageInUseService $imageInUseService,
    ) {}

    /**
     * @throws CannotDeleteEntityException
     * @throws ImageInUseException
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

        $references = $image->getPath() !== null
            ? $this->imageInUseService->findReferences($image->getPath(), $imageData->accountId)
            : [];

        $hasProtected = false;
        foreach ($references as $ref) {
            if ($ref->is_protected) {
                $hasProtected = true;
                break;
            }
        }

        if ($hasProtected) {
            throw new ImageInUseException(
                references: $references,
                hasProtectedReferences: true,
                message: __('Image is referenced by an active event and cannot be deleted.'),
            );
        }

        if (! empty($references) && ! $imageData->confirm) {
            throw new ImageInUseException(
                references: $references,
                hasProtectedReferences: false,
                message: __('Image is referenced by other content. Confirm to delete anyway.'),
            );
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
