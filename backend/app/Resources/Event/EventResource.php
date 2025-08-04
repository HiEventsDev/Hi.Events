<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Resources\Organizer\OrganizerResource;
use HiEvents\Resources\Product\ProductResource;
use HiEvents\Resources\ProductCategory\ProductCategoryResource;
use Illuminate\Http\Request;

/**
 * @mixin EventDomainObject
 */
class EventResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'category' => $this->getCategory(),
            'description' => $this->getDescription(),
            'start_date' => $this->getStartDate(),
            'end_date' => $this->getEndDate(),
            'status' => $this->getStatus(),
            'lifecycle_status' => $this->getLifeCycleStatus(),
            'currency' => $this->getCurrency(),
            'timezone' => $this->getTimezone(),
            'slug' => $this->getSlug(),
            'products' => $this->when(
                condition: (bool)$this->getProducts(),
                value: fn() => ProductResource::collection($this->getProducts()),
            ),
            'product_categories' => $this->when(
                condition: (bool)$this->getProductCategories(),
                value: fn() => ProductCategoryResource::collection($this->getProductCategories()),
            ),
            'attributes' => $this->when((bool)$this->getAttributes(), fn() => $this->getAttributes()),
            'images' => $this->when((bool)$this->getImages(), fn() => ImageResource::collection($this->getImages())),
            'location_details' => $this->when((bool)$this->getLocationDetails(), fn() => $this->getLocationDetails()),
            'settings' => $this->when(
                condition: !is_null($this->getEventSettings()),
                value: fn() => new EventSettingsResource($this->getEventSettings())
            ),
            'organizer' => $this->when(
                condition: !is_null($this->getOrganizer()),
                value: fn() => new OrganizerResource($this->getOrganizer())
            ),
            'statistics' => $this->when(
                condition: !is_null($this->getEventStatistics()),
                value: fn() => new EventStatisticsResource($this->getEventStatistics())
            ),
        ];
    }
}
