<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\HomepageSectionType;
use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;

class HomepageSectionImageCloneService
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
    ) {}

    public function clone(EventDomainObject $sourceEvent, int $newEventId): void
    {
        $settings = $this->eventSettingsRepository->findFirstWhere([EventSettingDomainObjectAbstract::EVENT_ID => $newEventId]);
        $sections = $settings?->getHomepageSections();

        if (! is_array($sections) || $sections === []) {
            return;
        }

        $sourceImages = ($sourceEvent->getImages() ?? collect())
            ->filter(fn (ImageDomainObject $image) => in_array($image->getType(), [ImageType::EVENT_GALLERY->name, ImageType::EVENT_SPONSOR_LOGO->name], true))
            ->keyBy(fn (ImageDomainObject $image) => $image->getId());

        $newImageIds = [];
        $copy = function (int $imageId) use ($sourceImages, $newEventId, &$newImageIds): ?int {
            if (! $sourceImages->has($imageId)) {
                return null;
            }

            return $newImageIds[$imageId] ??= $this->copyImage($sourceImages->get($imageId), $newEventId);
        };

        $remapped = array_map(function (array $section) use ($copy) {
            if (($section['type'] ?? null) === HomepageSectionType::GALLERY->value) {
                $section['image_ids'] = array_values(array_filter(array_map($copy, $section['image_ids'] ?? [])));
            }

            if (($section['type'] ?? null) === HomepageSectionType::SPONSORS->value) {
                $section['sponsors'] = array_values(array_filter(array_map(function (array $sponsor) use ($copy) {
                    $sponsor['image_id'] = $copy((int) ($sponsor['image_id'] ?? 0));

                    return $sponsor['image_id'] === null ? null : $sponsor;
                }, $section['sponsors'] ?? [])));
            }

            return $section;
        }, $sections);

        $this->eventSettingsRepository->updateFromArray($settings->getId(), [
            EventSettingDomainObjectAbstract::HOMEPAGE_SECTIONS => $remapped,
        ]);
    }

    private function copyImage(ImageDomainObject $image, int $newEventId): int
    {
        return $this->imageRepository->create([
            'account_id' => $image->getAccountId(),
            'entity_id' => $newEventId,
            'entity_type' => EventDomainObject::class,
            'type' => $image->getType(),
            'disk' => $image->getDisk(),
            'path' => $image->getPath(),
            'filename' => $image->getFilename(),
            'size' => $image->getSize(),
            'mime_type' => $image->getMimeType(),
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
            'avg_colour' => $image->getAvgColour(),
            'lqip_base64' => $image->getLqipBase64(),
            'sort_order' => $image->getSortOrder(),
        ])->getId();
    }
}
