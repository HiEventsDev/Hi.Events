<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Resources;

use HiEvents\DomainObjects\CrmContactActivityDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Resources\BaseResource;

/**
 * @mixin CrmContactActivityDomainObject
 */
class ContactActivityResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            /** @var 'ORDER_COMPLETED'|'ORDER_AWAITING_PAYMENT'|'ORDER_CANCELLED'|'ORDER_REFUNDED'|'CHECKOUT_ABANDONED'|'TICKET_HELD'|'CHECKED_IN'|'WAITLIST_JOINED'|'CAMPAIGN_SENT'|'IMPORTED' */
            'type' => $this->getType(),
            /** @var 'SELF'|'GUEST'|null */
            'role' => $this->getRole(),
            'event_id' => $this->getEventId(),
            'event_title' => $this->getEventTitle(),
            'event_timezone' => $this->getEventTimezone(),
            'occurrence_start_date' => $this->getOccurrenceStartDate(),
            'order_id' => $this->getOrderId(),
            'product_id' => $this->getProductId(),
            'product_title' => $this->getProductTitle(),
            'label' => $this->getLabel(),
            /** @var 'EMAIL'|'SMS'|null */
            'campaign_channel' => $this->getCampaignChannel(),
            'email' => $this->getEmail(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'occurred_at' => $this->getOccurredAt(),
            $this->mergeWhen($this->isPermitted(Permission::VIEW_FINANCIALS), fn () => [
                'amount' => $this->getAmount() === null ? null : (float) $this->getAmount(),
                'currency' => $this->getCurrency(),
            ]),
        ];
    }
}
