<?php

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\Http\DTO\QueryParamsDTO;

class GetPublicOrganizerEventsDTO extends BaseDTO
{
    public function __construct(
        public int            $organizerId,
        public QueryParamsDTO $queryParams,
        public ?int           $authenticatedAccountId = null,
    )
    {
    }
}
