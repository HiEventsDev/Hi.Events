<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\Generated\ImageDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProfileDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class SortProfileImagesHandler
{
    public function __construct(
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @param  int[]  $sortedImageIds
     *
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(int $organizerId, int $accountId, int $profileId, array $sortedImageIds): void
    {
        $profile = $this->profileRepository->findFirstWhere([
            ProfileDomainObjectAbstract::ID => $profileId,
            ProfileDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            ProfileDomainObjectAbstract::ACCOUNT_ID => $accountId,
        ]);

        if ($profile === null) {
            throw new ResourceNotFoundException(__('Profile not found'));
        }

        $where = [
            ImageDomainObjectAbstract::ENTITY_ID => $profileId,
            ImageDomainObjectAbstract::ENTITY_TYPE => ProfileDomainObject::class,
            ImageDomainObjectAbstract::TYPE => ImageType::PROFILE_IMAGE->name,
        ];

        $profileImageIds = $this->imageRepository
            ->findWhere($where, orderAndDirections: [
                new OrderAndDirection(ImageDomainObjectAbstract::SORT_ORDER),
                new OrderAndDirection(ImageDomainObjectAbstract::ID),
            ])
            ->map(fn (ImageDomainObject $image) => $image->getId())
            ->all();

        if (array_diff($sortedImageIds, $profileImageIds) !== []) {
            throw new ResourceNotFoundException(__('Photo not found'));
        }

        $ordered = array_values(array_unique([...$sortedImageIds, ...$profileImageIds]));

        $this->databaseManager->transaction(function () use ($ordered, $where) {
            foreach ($ordered as $position => $imageId) {
                $this->imageRepository->updateWhere(
                    attributes: [ImageDomainObjectAbstract::SORT_ORDER => $position],
                    where: [...$where, ImageDomainObjectAbstract::ID => $imageId],
                );
            }
        });
    }
}
