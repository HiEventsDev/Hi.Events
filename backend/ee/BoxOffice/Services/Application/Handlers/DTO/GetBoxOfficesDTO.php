<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Http\DTO\QueryParamsDTO;

class GetBoxOfficesDTO extends BaseDataObject
{
    public function __construct(
        public int $event_id,
        public QueryParamsDTO $query_params,
    ) {}
}
