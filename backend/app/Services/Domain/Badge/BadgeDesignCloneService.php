<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;

class BadgeDesignCloneService
{
    public function __construct(
        private readonly ImageRepositoryInterface $imageRepository,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
    ) {}

    /**
     * @param  array<int, int>  $oldToNewQuestionMap
     * @param  array<int, int>  $oldToNewProductMap
     */
    public function clone(
        EventDomainObject $sourceEvent,
        int $newEventId,
        array $oldToNewQuestionMap,
        array $oldToNewProductMap,
    ): void {
        $this->cloneImages($sourceEvent, $newEventId);

        /** @var EventSettingDomainObject|null $settings */
        $settings = $this->eventSettingsRepository->findFirstWhere([EventSettingDomainObjectAbstract::EVENT_ID => $newEventId]);
        $design = $settings?->getBadgeDesignSettings();

        if (! is_array($design) || $design === []) {
            return;
        }

        $design['lines'] = array_values(array_filter(array_map(
            function (array $line) use ($oldToNewQuestionMap): ?array {
                if (($line['source'] ?? null) !== BadgeFieldSource::QUESTION->value) {
                    return $line;
                }

                $newQuestionId = $oldToNewQuestionMap[(int) ($line['question_id'] ?? 0)] ?? null;

                return $newQuestionId === null ? null : [...$line, 'question_id' => $newQuestionId];
            },
            $design['lines'] ?? [],
        )));

        $design['ribbons'] = array_values(array_filter(array_map(
            function (array $ribbon) use ($oldToNewProductMap): ?array {
                $newProductId = $oldToNewProductMap[(int) ($ribbon['product_id'] ?? 0)] ?? null;

                return $newProductId === null ? null : [...$ribbon, 'product_id' => $newProductId];
            },
            $design['ribbons'] ?? [],
        )));

        $this->eventSettingsRepository->updateFromArray($settings->getId(), [
            EventSettingDomainObjectAbstract::BADGE_DESIGN_SETTINGS => $design['lines'] === [] ? null : $design,
        ]);
    }

    private function cloneImages(EventDomainObject $sourceEvent, int $newEventId): void
    {
        $badgeImageTypes = array_map(fn (ImageType $type) => $type->name, ImageType::badgeImageTypes());

        ($sourceEvent->getImages() ?? collect())
            ->filter(fn (ImageDomainObject $image) => in_array($image->getType(), $badgeImageTypes, true))
            ->each(fn (ImageDomainObject $image) => $this->imageRepository->create([
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
            ]));
    }
}
