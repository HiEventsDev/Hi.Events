<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\UserDomainObject;

class CreateBoxOfficeSessionDTO extends BaseDataObject
{
    public function __construct(
        public string $box_office_short_id,
        public string $operator_name,
        public ?string $pin = null,
        public ?int $event_occurrence_id = null,
        public ?int $stripe_terminal_reader_id = null,
        public ?string $ip_address = null,
        public ?UserDomainObject $authenticated_user = null,
        public ?int $authenticated_account_id = null,
    ) {}
}
