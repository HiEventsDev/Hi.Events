<?php

namespace HiEvents\Services\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\Http\DTO\QueryParamsDTO;

class GetEventsDTO extends BaseDTO
{
    public function __construct(
        public int $accountId,
        public QueryParamsDTO $queryParams,
    )
    {
    }
}
