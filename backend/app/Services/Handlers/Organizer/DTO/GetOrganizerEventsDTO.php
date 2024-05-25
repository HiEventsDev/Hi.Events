<?php

namespace HiEvents\Services\Handlers\Organizer\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\Http\DTO\QueryParamsDTO;

class GetOrganizerEventsDTO extends BaseDTO
{
    public function __construct(
        public int            $organizerId,
        public int            $accountId,
        public QueryParamsDTO $queryParams
    )
    {
    }
}
