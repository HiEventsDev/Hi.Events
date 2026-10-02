<?php

namespace HiEvents\Enterprise\BoxOffice\Resources;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Enterprise\BoxOffice\Resources\Organizer\Stripe\StripeTerminalReaderResource;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficePublicDTO;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResourcePublic;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin BoxOfficePublicDTO
 */
class BoxOfficeResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        $boxOffice = $this->box_office;
        $event = $boxOffice->getEvent();

        return [
            'short_id' => $boxOffice->getShortId(),
            'name' => $boxOffice->getName(),
            'description' => $boxOffice->getDescription(),
            'has_pin' => $boxOffice->hasPin(),
            'is_expired' => $boxOffice->isExpired(),
            'is_active' => $boxOffice->isActivated(),
            'activates_at' => $boxOffice->getActivatesAt(),
            'allow_price_override' => $boxOffice->getAllowPriceOverride(),
            'allow_discounts' => $boxOffice->getAllowDiscounts(),
            'collect_order_questions' => $boxOffice->getCollectOrderQuestions(),
            'card_payments_enabled' => $this->card_payments_enabled,
            'can_skip_pin' => $this->can_skip_pin,
            'readers' => StripeTerminalReaderResource::collection($this->readers),
            'occurrences' => EventOccurrenceResourcePublic::collection($this->selectableOccurrences($boxOffice)),
            'event' => [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'timezone' => $event->getTimezone(),
                'currency' => $event->getCurrency(),
                /** @var 'SINGLE'|'RECURRING' */
                'type' => $event->getType(),
                /** @var 'DRAFT'|'LIVE'|'ARCHIVED'|'PENDING_MANUAL_REVIEW' */
                'status' => $event->getStatus(),
                'price_display_mode' => $event->getEventSettings()?->getPriceDisplayMode(),
            ],
        ];
    }

    private function selectableOccurrences(BoxOfficeDomainObject $boxOffice): Collection
    {
        $event = $boxOffice->getEvent();

        if ($boxOffice->getEventOccurrenceId() !== null || $event->getType() !== EventType::RECURRING->name) {
            return collect();
        }

        return ($event->getEventOccurrences() ?? collect())
            ->reject(fn (EventOccurrenceDomainObject $occurrence) => $occurrence->isCancelled())
            ->sortBy(fn (EventOccurrenceDomainObject $occurrence) => $occurrence->getStartDate())
            ->values();
    }
}
