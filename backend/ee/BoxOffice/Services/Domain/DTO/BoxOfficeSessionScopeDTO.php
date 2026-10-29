<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;

class BoxOfficeSessionScopeDTO extends BaseDataObject
{
    public function __construct(
        public ?EventOccurrenceDomainObject $event_occurrence,
        public ?string $check_in_list_short_id,
        public bool $check_in_available,
        public ?string $check_in_unavailable_reason,
    ) {}
}
