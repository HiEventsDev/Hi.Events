<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\DTO\EventSpamCheckContentDTO;

class EventSpamCheckContentService
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function loadEvent(int $eventId): ?EventDomainObject
    {
        /** @var EventDomainObject|null $event */
        $event = $this->eventRepository
            ->loadRelation(new Relationship(domainObject: OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(new Relationship(domainObject: EventSettingDomainObject::class, name: 'event_settings'))
            ->loadRelation(new Relationship(domainObject: ProductDomainObject::class))
            ->loadRelation(new Relationship(domainObject: ProductCategoryDomainObject::class))
            ->findFirstWhere(['id' => $eventId]);

        return $event;
    }

    public function buildForEvent(EventDomainObject $event): EventSpamCheckContentDTO
    {
        return new EventSpamCheckContentDTO(
            title: $event->getTitle(),
            description: $event->getDescription(),
            supplementaryContent: array_filter([
                ...$this->organizerContent($event),
                ...$this->settingsContent($event),
                ...$this->productContent($event),
                ...$this->categoryContent($event),
            ], static fn (?string $value): bool => $value !== null && trim($value) !== ''),
        );
    }

    /**
     * @return array<string, ?string>
     */
    private function organizerContent(EventDomainObject $event): array
    {
        $organizer = $event->getOrganizer();

        if ($organizer === null) {
            return [];
        }

        return [
            'organizer name' => $organizer->getName(),
            'organizer description' => $organizer->getDescription(),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private function settingsContent(EventDomainObject $event): array
    {
        $settings = $event->getEventSettings();

        if ($settings === null) {
            return [];
        }

        return [
            'product page message' => $settings->getProductPageMessage(),
            'pre-checkout message' => $settings->getPreCheckoutMessage(),
            'post-checkout message' => $settings->getPostCheckoutMessage(),
            'offline payment instructions' => $settings->getOfflinePaymentInstructions(),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private function productContent(EventDomainObject $event): array
    {
        $content = [];

        foreach ($event->getProducts() ?? [] as $index => $product) {
            /** @var ProductDomainObject $product */
            $content['product '.($index + 1).' title'] = $product->getTitle();
            $content['product '.($index + 1).' description'] = $product->getDescription();
        }

        return $content;
    }

    /**
     * @return array<string, ?string>
     */
    private function categoryContent(EventDomainObject $event): array
    {
        $content = [];

        foreach ($event->getProductCategories() ?? [] as $index => $category) {
            /** @var ProductCategoryDomainObject $category */
            $content['category '.($index + 1).' name'] = $category->getName();
            $content['category '.($index + 1).' description'] = $category->getDescription();
        }

        return $content;
    }
}
