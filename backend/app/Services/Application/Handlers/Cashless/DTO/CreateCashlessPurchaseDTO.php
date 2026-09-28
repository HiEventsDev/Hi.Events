<?php

namespace HiEvents\Services\Application\Handlers\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Services\Domain\Cashless\DTO\CashlessPurchaseItemRequestDTO;
use Illuminate\Support\Collection;

class CreateCashlessPurchaseDTO extends BaseDataObject
{
    public function __construct(
        public string $sales_point_short_id,
        public string $attendee_public_id,
        /** @var Collection<CashlessPurchaseItemRequestDTO> */
        public Collection $items,
        public string $client_reference_id,
        public string $locale,
        public ?string $session_token = null,
    ) {}
}
