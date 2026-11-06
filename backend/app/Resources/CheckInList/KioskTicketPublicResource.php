<?php

declare(strict_types=1);

namespace HiEvents\Resources\CheckInList;

use HiEvents\DomainObjects\AttendeeDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeDomainObject
 */
class KioskTicketPublicResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'public_id' => $this->getPublicId(),
            'first_name' => $this->getFirstName(),
            'product_title' => $this->getProduct()?->getTitle(),
        ];
    }
}
