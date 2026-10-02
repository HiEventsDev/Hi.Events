<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use Illuminate\Support\Collection;

class BoxOfficePublicDTO extends BaseDataObject
{
    public function __construct(
        public BoxOfficeDomainObject $box_office,
        public Collection $readers,
        public bool $card_payments_enabled,
        public bool $can_skip_pin,
    ) {}
}
