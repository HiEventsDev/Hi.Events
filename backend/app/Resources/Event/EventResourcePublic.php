<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Resources\BaseResource;
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
    )
    {
        // This is a hacky workaround to handle when this resource is instantiated
        // internally within Laravel the second param is the collection key (numeric)
        // When called normally, second param is includePostCheckoutData (boolean)
        $this->includePostCheckoutData = is_bool($includePostCheckoutData)
            ? $includePostCheckoutData
            : false;

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'category' => $this->getCategory(),
            'description' => $this->getDescription(),
            'description_preview' => $this->getDescriptionPreview(),
            'start_date' => $this->getStartDate(),
            'end_date' => $this->getEndDate(),
            'currency' => $this->getCurrency(),
            'slug' => $this->getSlug(),
            'status' => $this->getStatus(),
            'lifecycle_status' => $this->getLifecycleStatus(),
            'timezone' => $this->getTimezone(),
            'location_details' => $this->when((bool)$this->getLocationDetails(), fn() => $this->getLocationDetails()),
            'product_categories' => $this->when(
                condition: !is_null($this->getProductCategories()) && $this->getProductCategories()->isNotEmpty(),
                value: fn() => ProductCategoryResourcePublic::collection($this->getProductCategories()),
            ),
            'settings' => $this->when(
                condition: !is_null($this->getEventSettings()),
                value: fn() => new EventSettingsResourcePublic(
                    $this->getEventSettings(),
                    $this->includePostCheckoutData
                ),
            ),
            // @TODO - public question resource
            'questions' => $this->when(
                condition: !is_null($this->getQuestions()),
                value: fn() => QuestionResource::collection($this->getQuestions())
            ),
            'attributes' => $this->when(
                condition: !is_null($this->getAttributes()),
                value: fn() => collect($this->getAttributes())->reject(fn($attribute) => !$attribute['is_public'])),
            'images' => $this->when(
                condition: !is_null($this->getImages()),
                value: fn() => ImageResource::collection($this->getImages())
            ),
            'organizer' => $this->when(
                condition: !is_null($this->getOrganizer()),
                value: fn() => new OrganizerResourcePublic($this->getOrganizer()),
            ),
        ];
    }
}
