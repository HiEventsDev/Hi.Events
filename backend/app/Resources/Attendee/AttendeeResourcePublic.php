<?php

namespace HiEvents\Resources\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Resources\EventOccurrence\EventOccurrenceResourcePublic;
use HiEvents\Resources\Product\ProductMinimalResourcePublic;
use HiEvents\Resources\TicketResale\TicketResaleStateResourcePublic;
use HiEvents\Resources\TicketTransfer\TicketTransferResourcePublic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeDomainObject
 */
class AttendeeResourcePublic extends JsonResource
{
    public function __construct($resource, private readonly ?bool $includeOnlineConnectionDetails = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $includeOnlineConnectionDetails = $this->includeOnlineConnectionDetails
            ?? ($this->getStatus() === AttendeeStatus::ACTIVE->name);

        return [
            'id' => $this->getId(),
            'email' => $this->getEmail(),
            /** @var 'ACTIVE'|'AWAITING_PAYMENT'|'CANCELLED' */
            'status' => $this->getStatus(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'public_id' => $this->getPublicId(),
            'short_id' => $this->getShortId(),
            'seat_uid' => $this->getSeatUid(),
            'seat_label' => $this->getSeatLabel(),
            'product_id' => $this->getProductId(),
            'product_price_id' => $this->getProductPriceId(),
            'product' => $this->when((bool) $this->getProduct(), fn () => new ProductMinimalResourcePublic($this->getProduct())),
            'event_occurrence_id' => $this->getEventOccurrenceId(),
            'event_occurrence' => $this->when(
                (bool) $this->getEventOccurrence(),
                fn () => new EventOccurrenceResourcePublic(
                    $this->getEventOccurrence(),
                    includeOnlineConnectionDetails: $includeOnlineConnectionDetails,
                ),
            ),
            'locale' => $this->getLocale(),
            'transfer' => $this->when($this->getTransferState() !== null, fn () => [
                'can_start' => $this->getTransferState()->can_start,
                'blocked_reason' => $this->getTransferState()->blocked_reason,
                'pending' => $this->getTransferState()->pending === null
                    ? null
                    : new TicketTransferResourcePublic($this->getTransferState()->pending),
            ]),
            'resale' => $this->when($this->getResaleState() !== null, fn () => new TicketResaleStateResourcePublic($this->getResaleState())),
            'is_checked_in' => $this->when(
                $this->getCheckIns() !== null,
                fn () => $this->getCheckIns()->isNotEmpty(),
            ),
        ];
    }
}
