<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\DTO\EventSpamCheckContentDTO;
use HiEvents\Services\Domain\Lineup\LineupHighlightSelector;
use Illuminate\Support\Collection;

class EventSpamCheckContentService
{
    public const TICKET_EMAIL_LABEL_PREFIX = 'ticket email ';

    private const LINEUP_TIER_RANKS = [
        LineupBillingTier::HEADLINER->value => 0,
        LineupBillingTier::FEATURED->value => 1,
        LineupBillingTier::STANDARD->value => 2,
    ];

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly LineupHighlightSelector $lineupHighlightSelector,
    ) {}

    public function loadEvent(int $eventId): ?EventDomainObject
    {
        /** @var EventDomainObject|null $event */
        $event = $this->eventRepository
            ->loadRelation(new Relationship(domainObject: OrganizerDomainObject::class, nested: [
                new Relationship(domainObject: OrganizerSettingDomainObject::class, name: 'organizer_settings'),
            ], name: 'organizer'))
            ->loadRelation(new Relationship(domainObject: EventSettingDomainObject::class, name: 'event_settings'))
            ->loadRelation(new Relationship(domainObject: ProductDomainObject::class))
            ->loadRelation(new Relationship(domainObject: ProductCategoryDomainObject::class))
            ->loadRelation(new Relationship(domainObject: QuestionDomainObject::class))
            ->loadRelation(new Relationship(domainObject: EventLineupEntryDomainObject::class, nested: [
                new Relationship(domainObject: ProfileDomainObject::class, name: 'profile'),
            ]))
            ->loadRelation(new Relationship(domainObject: EventSpaceDomainObject::class))
            ->loadRelation(new Relationship(domainObject: EventScheduleItemDomainObject::class, nested: [
                new Relationship(domainObject: EventScheduleItemPerformerDomainObject::class, name: 'performers'),
            ]))
            ->findFirstWhere(['id' => $eventId]);

        return $event;
    }

    public function buildForEvent(EventDomainObject $event): EventSpamCheckContentDTO
    {
        $lineupEntries = $this->lineupEntriesInEmailOrder($event);
        $scheduleItems = $this->scheduleItemsInTimeOrder($event);
        $ticketEmailLineup = $this->ticketEmailLineupContent($event, $lineupEntries, $scheduleItems);

        return new EventSpamCheckContentDTO(
            title: $event->getTitle(),
            description: $event->getDescription(),
            supplementaryContent: array_filter([
                ...$this->withLabelPrefix(self::TICKET_EMAIL_LABEL_PREFIX, $ticketEmailLineup),
                ...array_diff_key($this->lineupHeadlineContent($event, $lineupEntries, $scheduleItems), $ticketEmailLineup),
                ...$this->organizerContent($event),
                ...$this->settingsContent($event),
                ...$this->productContent($event),
                ...$this->categoryContent($event),
                ...$this->questionContent($event),
                ...$this->lineupContent($lineupEntries),
                ...$this->scheduleContent($scheduleItems),
            ], static fn (?string $value): bool => $value !== null && trim($value) !== ''),
        );
    }

    /**
     * @param  Collection<int, EventLineupEntryDomainObject>  $lineupEntries
     * @param  Collection<int, EventScheduleItemDomainObject>  $scheduleItems
     * @return array<string, ?string>
     */
    private function ticketEmailLineupContent(EventDomainObject $event, Collection $lineupEntries, Collection $scheduleItems): array
    {
        $selection = $this->lineupHighlightSelector->select(
            $lineupEntries->filter(
                static fn (EventLineupEntryDomainObject $entry): bool => ! $entry->getIsHidden() && $entry->getProfile() !== null
            ),
            $scheduleItems,
        );
        $spaces = collect($event->getEventSpaces() ?? [])->values();
        $slotContent = [];

        foreach ($selection->slots as $index => $slot) {
            /** @var EventScheduleItemDomainObject $item */
            $item = $slot['item'];

            if ($item->getTitle() !== null) {
                $slotContent['schedule item '.($index + 1).' title'] = $item->getTitle();
            } else {
                $slotContent = [...$slotContent, ...$this->lineupNameContent($slot['people'])];
            }

            $spaceIndex = $item->getEventSpaceId() === null
                ? false
                : $spaces->search(static fn (EventSpaceDomainObject $space): bool => $space->getId() === $item->getEventSpaceId());

            if ($spaceIndex !== false) {
                $slotContent['space '.($spaceIndex + 1).' name'] = $spaces[$spaceIndex]->getName();
            }
        }

        return [
            ...$this->lineupNameContent($selection->entries),
            ...$slotContent,
        ];
    }

    /**
     * @param  array<string, ?string>  $content
     * @return array<string, ?string>
     */
    private function withLabelPrefix(string $prefix, array $content): array
    {
        return collect($content)
            ->mapWithKeys(static fn (?string $value, string $label): array => [$prefix.$label => $value])
            ->all();
    }

    /**
     * @param  Collection<int, EventLineupEntryDomainObject>  $entries
     * @return array<string, ?string>
     */
    private function lineupNameContent(Collection $entries): array
    {
        $content = [];

        foreach ($entries as $index => $entry) {
            $content['lineup '.($index + 1).' name'] = $entry->getProfile()?->getName();
        }

        return $content;
    }

    /**
     * @param  Collection<int, EventLineupEntryDomainObject>  $lineupEntries
     * @param  Collection<int, EventScheduleItemDomainObject>  $scheduleItems
     * @return array<string, ?string>
     */
    private function lineupHeadlineContent(EventDomainObject $event, Collection $lineupEntries, Collection $scheduleItems): array
    {
        $content = $this->lineupNameContent($lineupEntries->reject(
            static fn (EventLineupEntryDomainObject $entry): bool => $entry->getIsHidden()
        ));

        foreach ($scheduleItems as $index => $item) {
            $content['schedule item '.($index + 1).' title'] = $item->getTitle();
        }

        foreach ($event->getEventSpaces() ?? [] as $index => $space) {
            /** @var EventSpaceDomainObject $space */
            $content['space '.($index + 1).' name'] = $space->getName();
        }

        return $content;
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

        $settings = $organizer->getOrganizerSettings();

        return [
            'organizer name' => $organizer->getName(),
            'organizer description' => $organizer->getDescription(),
            'organizer website' => $organizer->getWebsite(),
            'organizer settings website' => $settings?->getWebsiteUrl(),
            'organizer seo title' => $settings?->getSeoTitle(),
            'organizer seo description' => $settings?->getSeoDescription(),
            'organizer seo keywords' => $settings?->getSeoKeywords(),
            ...$this->homepageSectionContent('organizer homepage section', $settings?->getHomepageSections() ?? []),
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
            'email footer message' => $settings->getEmailFooterMessage(),
            'online event connection details' => $settings->getOnlineEventConnectionDetails(),
            'maps url' => $settings->getMapsUrl(),
            'seo title' => $settings->getSeoTitle(),
            'seo description' => $settings->getSeoDescription(),
            'seo keywords' => $settings->getSeoKeywords(),
            ...$this->homepageSectionContent('homepage section', $settings->getHomepageSections() ?? []),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private function homepageSectionContent(string $label, array $sections): array
    {
        $content = [];

        foreach (array_values($sections) as $index => $section) {
            $prefix = $label.' '.($index + 1);
            $content[$prefix.' heading'] = $section['heading'] ?? null;
            $content[$prefix.' body'] = $section['body'] ?? null;
            $content[$prefix.' video'] = $section['video_url'] ?? null;

            foreach ($section['faqs'] ?? [] as $faqIndex => $faq) {
                $content[$prefix.' question '.($faqIndex + 1)] = $faq['question'] ?? null;
                $content[$prefix.' answer '.($faqIndex + 1)] = $faq['answer'] ?? null;
            }

            foreach ($section['sponsors'] ?? [] as $sponsorIndex => $sponsor) {
                $content[$prefix.' sponsor '.($sponsorIndex + 1)] = trim(($sponsor['name'] ?? '').' '.($sponsor['url'] ?? '')) ?: null;
            }
        }

        return $content;
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

    /**
     * @return array<string, ?string>
     */
    private function questionContent(EventDomainObject $event): array
    {
        $content = [];

        foreach ($event->getQuestions() ?? [] as $index => $question) {
            /** @var QuestionDomainObject $question */
            $content['question '.($index + 1).' title'] = $question->getTitle();
            $content['question '.($index + 1).' description'] = $question->getDescription();
        }

        return $content;
    }

    /**
     * @param  Collection<int, EventLineupEntryDomainObject>  $lineupEntries
     * @return array<string, ?string>
     */
    private function lineupContent(Collection $lineupEntries): array
    {
        $content = [];

        foreach ($lineupEntries as $index => $entry) {
            $profile = $entry->getProfile();
            $prefix = 'lineup '.($index + 1);

            if ($entry->getIsHidden()) {
                $content[$prefix.' name'] = $profile?->getName();
            }

            $content[$prefix.' role'] = $entry->getRoleLabel();

            if ($profile === null) {
                continue;
            }

            $content[$prefix.' tagline'] = $profile->getTagline();
            $content[$prefix.' bio'] = $profile->getBio();
            $content[$prefix.' links'] = implode(' ', array_column($profile->getLinks() ?? [], 'url'));
        }

        return $content;
    }

    /**
     * @param  Collection<int, EventScheduleItemDomainObject>  $scheduleItems
     * @return array<string, ?string>
     */
    private function scheduleContent(Collection $scheduleItems): array
    {
        $content = [];

        foreach ($scheduleItems as $index => $item) {
            $content['schedule item '.($index + 1).' description'] = $item->getDescription();
        }

        return $content;
    }

    /**
     * @return Collection<int, EventLineupEntryDomainObject>
     */
    private function lineupEntriesInEmailOrder(EventDomainObject $event): Collection
    {
        $orderKey = static fn (EventLineupEntryDomainObject $entry): array => [
            (bool) $entry->getIsHidden(),
            self::LINEUP_TIER_RANKS[$entry->getBillingTier()] ?? count(self::LINEUP_TIER_RANKS),
            $entry->getSortOrder(),
            $entry->getId(),
        ];

        return collect($event->getEventLineupEntries() ?? [])
            ->sort(static fn (EventLineupEntryDomainObject $a, EventLineupEntryDomainObject $b): int => $orderKey($a) <=> $orderKey($b))
            ->values();
    }

    /**
     * @return Collection<int, EventScheduleItemDomainObject>
     */
    private function scheduleItemsInTimeOrder(EventDomainObject $event): Collection
    {
        return collect($event->getEventScheduleItems() ?? [])
            ->sort(static fn (EventScheduleItemDomainObject $a, EventScheduleItemDomainObject $b): int => [$a->getStartsAt(), $a->getId()] <=> [$b->getStartsAt(), $b->getId()])
            ->values();
    }
}
