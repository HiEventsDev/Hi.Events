<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;

class UpdateBoxOfficeSessionDTO extends BaseDataObject
{
    public function __construct(
        public string $box_office_short_id,
        public string $token,
        public BoxOfficeSessionDTO $session,
        public ?int $event_occurrence_id = null,
        public bool $change_reader = false,
        public ?int $stripe_terminal_reader_id = null,
    ) {}
}
