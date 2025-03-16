<?php

namespace HiEvents\Resources\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Resources\CheckInList\AttendeeCheckInPublicResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeDomainObject
 */
class AttendeeWithCheckInPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'email' => $this->getEmail(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'public_id' => $this->getPublicId(),
            'product_id' => $this->getProductId(),
            'product_price_id' => $this->getProductPriceId(),
            'status' => $this->getStatus(),
            'locale' => $this->getLocale(),
            'order_id' => $this->getOrderId(),
            $this->mergeWhen($this->getCheckIn() !== null, [
                'check_in' => new AttendeeCheckInPublicResource($this->getCheckIn()),
            ]),
        ];
    }
}
