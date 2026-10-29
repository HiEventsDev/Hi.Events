<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionReaderDTO;

class BoxOfficeSessionCreatedDTO extends BaseDataObject
{
    public function __construct(
        public string $token,
        public string $expires_at,
        public string $operator_name,
        public ?EventOccurrenceDomainObject $event_occurrence,
        public ?BoxOfficeSessionReaderDTO $reader,
        public ?string $check_in_list_short_id,
        public bool $check_in_available,
        public ?string $check_in_unavailable_reason,
    ) {}
}
