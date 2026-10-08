<?php

namespace HiEvents\Enterprise\BoxOffice\Resources;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResource;
use HiEvents\Resources\Product\ProductResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoxOfficeDomainObject
 */
class BoxOfficeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'short_id' => $this->getShortId(),
            'event_id' => $this->getEventId(),
            'event_occurrence_id' => $this->getEventOccurrenceId(),
            'check_in_list_id' => $this->getCheckInListId(),
            'has_pin' => $this->hasPin(),
            'allow_price_override' => $this->getAllowPriceOverride(),
            'allow_discounts' => $this->getAllowDiscounts(),
            'collect_order_questions' => $this->getCollectOrderQuestions(),
            'is_system_default' => $this->getIsSystemDefault(),
            'activates_at' => $this->getActivatesAt(),
            'expires_at' => $this->getExpiresAt(),
            'sales_count' => $this->getSalesCount(),
            'gross_sales' => $this->getGrossSales(),
            $this->mergeWhen($this->getEvent() !== null, fn () => [
                'is_expired' => $this->isExpired(),
                'is_active' => $this->isActivated(),
                'currency' => $this->getEvent()->getCurrency(),
            ]),
            'event_occurrence' => $this->when(
                ! is_null($this->getEventOccurrence()),
                fn () => new EventOccurrenceResource($this->getEventOccurrence()),
            ),
            'check_in_list' => $this->when(
                ! is_null($this->getCheckInList()),
                fn () => [
                    'id' => $this->getCheckInList()->getId(),
                    'short_id' => $this->getCheckInList()->getShortId(),
                    'name' => $this->getCheckInList()->getName(),
                ],
            ),
            $this->mergeWhen($this->getProducts() !== null, fn () => [
                'products' => ProductResource::collection($this->getProducts()),
            ]),
        ];
    }
}
