<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BoxOfficeSessionDTO extends BaseDataObject
{
    public function __construct(
        public int $box_office_id,
        public int $event_id,
        public string $operator_name,
        public ?int $event_occurrence_id,
        public ?int $stripe_terminal_reader_id,
        public ?string $pin_hash,
        public string $expires_at,
        public ?int $authenticated_user_id = null,
        public ?int $authenticated_account_id = null,
    ) {}
}
