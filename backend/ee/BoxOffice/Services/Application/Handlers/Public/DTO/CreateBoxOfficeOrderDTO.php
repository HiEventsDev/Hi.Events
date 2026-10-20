<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use Illuminate\Support\Collection;

class CreateBoxOfficeOrderDTO extends BaseDataObject
{
    /**
     * @param  Collection<BoxOfficeOrderItemDTO>  $items
     */
    public function __construct(
        public BoxOfficeDomainObject $box_office,
        public BoxOfficeSessionDTO $session,
        public string $idempotency_key,
        public Collection $items,
        public ?BoxOfficeDiscountDTO $discount,
        public BoxOfficeBuyerDTO $buyer,
        public array $questions,
        public string $locale,
        public string $ip_address,
        public ?string $user_agent,
        public array $attendees = [],
    ) {}
}
