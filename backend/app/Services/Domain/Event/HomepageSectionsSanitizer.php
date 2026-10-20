<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\HomepageSectionType;
use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\ImageDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;

class HomepageSectionsSanitizer
{
    public function __construct(
        private readonly HtmlPurifierService $purifier,
        private readonly ImageRepositoryInterface $imageRepository,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeForEvent(array $sections, int $eventId): array
    {
        return $this->sanitize(
            sections: $sections,
            builtIns: HomepageSectionType::eventBuiltIns(),
            required: HomepageSectionType::TICKETS,
            galleryImageIds: $this->imageIds($eventId, EventDomainObject::class, ImageType::EVENT_GALLERY),
            sponsorImageIds: $this->imageIds($eventId, EventDomainObject::class, ImageType::EVENT_SPONSOR_LOGO),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeForOrganizer(array $sections, int $organizerId): array
    {
        return $this->sanitize(
            sections: $sections,
            builtIns: HomepageSectionType::organizerBuiltIns(),
            required: HomepageSectionType::EVENTS,
            galleryImageIds: $this->imageIds($organizerId, OrganizerDomainObject::class, ImageType::ORGANIZER_GALLERY),
            sponsorImageIds: $this->imageIds($organizerId, OrganizerDomainObject::class, ImageType::ORGANIZER_SPONSOR_LOGO),
        );
    }

    /**
     * @param  HomepageSectionType[]  $builtIns
     * @param  int[]  $galleryImageIds
     * @param  int[]  $sponsorImageIds
     */
    private function sanitize(
        array $sections,
        array $builtIns,
        HomepageSectionType $required,
        array $galleryImageIds,
        array $sponsorImageIds,
    ): array {
        $allowed = [...$builtIns, ...HomepageSectionType::customTypes()];
        $seenBuiltIns = [];
        $clean = [];

        foreach ($sections as $section) {
            $type = HomepageSectionType::from($section['type']);

            if (! in_array($type, $allowed, true)) {
                continue;
            }

            if (in_array($type, $builtIns, true)) {
                if (in_array($type, $seenBuiltIns, true)) {
                    continue;
                }
                $seenBuiltIns[] = $type;
            }

            $clean[] = array_filter([
                'id' => (string) $section['id'],
                'type' => $type->value,
                'enabled' => $type === $required || (bool) ($section['enabled'] ?? true),
                'heading' => $type === HomepageSectionType::TICKETS ? null : $this->plainText($section['heading'] ?? null),
                ...$this->content($type, $section, $galleryImageIds, $sponsorImageIds),
            ], static fn ($value) => $value !== null);
        }

        if (! in_array($required, $seenBuiltIns, true)) {
            array_unshift($clean, ['id' => strtolower($required->value), 'type' => $required->value, 'enabled' => true]);
        }

        return $clean;
    }

    /**
     * @param  int[]  $galleryImageIds
     * @param  int[]  $sponsorImageIds
     */
    private function content(HomepageSectionType $type, array $section, array $galleryImageIds, array $sponsorImageIds): array
    {
        return match ($type) {
            HomepageSectionType::TEXT => ['body' => $this->html($section['body'] ?? null)],
            HomepageSectionType::FAQ => ['faqs' => collect($section['faqs'] ?? [])
                ->map(fn (array $faq) => [
                    'question' => $this->plainText($faq['question'] ?? null),
                    'answer' => $this->html($faq['answer'] ?? null),
                ])
                ->filter(fn (array $faq) => $faq['question'] !== null)
                ->values()
                ->all()],
            HomepageSectionType::VIDEO => ['video_url' => isset($section['video_url']) && HomepageSectionType::isVideoUrl($section['video_url'])
                ? trim($section['video_url'])
                : null],
            HomepageSectionType::GALLERY => ['image_ids' => collect($section['image_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => in_array($id, $galleryImageIds, true))
                ->unique()
                ->values()
                ->all()],
            HomepageSectionType::SPONSORS => ['sponsors' => collect($section['sponsors'] ?? [])
                ->filter(fn (array $sponsor) => in_array((int) ($sponsor['image_id'] ?? 0), $sponsorImageIds, true))
                ->map(fn (array $sponsor) => array_filter([
                    'image_id' => (int) $sponsor['image_id'],
                    'name' => $this->plainText($sponsor['name'] ?? null),
                    'url' => isset($sponsor['url']) && str_starts_with(trim($sponsor['url']), 'https://') ? trim($sponsor['url']) : null,
                ], static fn ($value) => $value !== null))
                ->values()
                ->all()],
            default => [],
        };
    }

    /**
     * @return int[]
     */
    private function imageIds(int $entityId, string $entityType, ImageType $type): array
    {
        return $this->imageRepository
            ->findWhere([
                ImageDomainObjectAbstract::ENTITY_ID => $entityId,
                ImageDomainObjectAbstract::ENTITY_TYPE => $entityType,
                ImageDomainObjectAbstract::TYPE => $type->name,
            ])
            ->map(fn (ImageDomainObject $image) => $image->getId())
            ->all();
    }

    private function plainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(strip_tags($value));

        return $text === '' ? null : $text;
    }

    private function html(?string $value): ?string
    {
        $clean = $this->purifier->purify($value);

        return $clean === null || trim(strip_tags($clean)) === '' ? null : $clean;
    }
}
