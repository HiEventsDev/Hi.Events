<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\BoxOfficeTender;

class TenderBoxOfficeOrderDTO extends BaseDataObject
{
    public function __construct(
        public BoxOfficeDomainObject $box_office,
        public string $order_short_id,
        public BoxOfficeTender $tender,
        public ?float $amount_tendered = null,
        public ?string $reference = null,
        public ?int $stripe_terminal_reader_id = null,
    ) {}
}
