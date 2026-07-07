<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\EventLocation\EventLocationResourcePublic;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResourcePublic;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Resources\Organizer\OrganizerResourcePublic;
use HiEvents\Resources\ProductCategory\ProductCategoryResourcePublic;
use HiEvents\Resources\Question\QuestionResource;
use Illuminate\Http\Request;

/**
 * @mixin EventDomainObject
 */
class EventResourcePublic extends BaseResource
{
    private readonly bool $includePostCheckoutData;

    public function __construct(
        mixed $resource,
        mixed $includePostCheckoutData = false,
    ) {
        // Laravel passes a numeric collection key as the second arg during
        // collection iteration; coerce to false unless the caller passed a bool.
        $this->includePostCheckoutData = is_bool($includePostCheckoutData)
            ? $includePostCheckoutData
            : false;

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $isRecurring = $this->getType() === EventType::RECURRING->name;

        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'category' => $this->getCategory(),
            'description' => $this->getDescription(),
            'description_preview' => $this->getDescriptionPreview(),
            'start_date' => $this->getStartDate(),
            'end_date' => $this->getEndDate(),
            'next_occurrence_start_date' => $this->getNextOccurrenceStartDate(),
            'type' => $this->getType(),
            'currency' => $this->getCurrency(),
            'slug' => $this->getSlug(),
            'status' => $this->getStatus(),
            'lifecycle_status' => $this->getLifecycleStatus(),
            'timezone' => $this->getTimezone(),
            'event_location' => $this->when(
                condition: $this->getEventLocation() !== null,
                value: fn () => new EventLocationResourcePublic($this->getEventLocation(), $this->includePostCheckoutData),
            ),
            'product_categories' => $this->when(
                condition: ! is_null($this->getProductCategories()) && $this->getProductCategories()->isNotEmpty(),
                value: fn () => ProductCategoryResourcePublic::collection($this->getProductCategories()),
            ),
            'settings' => $this->when(
                condition: ! is_null($this->getEventSettings()),
                value: fn () => new EventSettingsResourcePublic(
                    $this->getEventSettings(),
                    $this->includePostCheckoutData
                ),
            ),
            // @TODO - public question resource
            'questions' => $this->when(
                condition: ! is_null($this->getQuestions()),
                value: fn () => QuestionResource::collection($this->getQuestions())
            ),
            'attributes' => $this->when(
                condition: ! is_null($this->getAttributes()),
                value: fn () => collect($this->getAttributes())->reject(fn ($attribute) => ! $attribute['is_public'])),
            'images' => $this->when(
                condition: ! is_null($this->getImages()),
                value: fn () => ImageResource::collection($this->getImages())
            ),
            'organizer' => $this->when(
                condition: ! is_null($this->getOrganizer()),
                value: fn () => new OrganizerResourcePublic($this->getOrganizer()),
            ),
            'occurrences' => $this->when(
                condition: ! is_null($this->getEventOccurrences()) && $this->getEventOccurrences()->isNotEmpty(),
                // Cap is enforced by GetPublicEventHandler; do not re-cap here
                // or shared/checkout links past the cap silently drop out.
                value: function () use ($isRecurring) {
                    $showCapacity = $this->getEventSettings()?->getShowAvailableOccurrenceCapacity() ?? false;

                    return $this->getEventOccurrences()
                        ->filter(fn (EventOccurrenceDomainObject $occ) => ! $occ->isCancelled() && (! $isRecurring || ! $occ->isPast()))
                        ->sortBy(fn (EventOccurrenceDomainObject $occ) => $occ->getStartDate())
                        ->values()
                        ->map(fn (EventOccurrenceDomainObject $occ) => new EventOccurrenceResourcePublic($occ, $showCapacity));
                },
            ),
        ];
    }
}
