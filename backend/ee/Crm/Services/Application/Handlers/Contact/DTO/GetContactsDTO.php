<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Http\DTO\QueryParamsDTO;

class GetContactsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly QueryParamsDTO $query_params,
        public readonly SegmentReferenceDTO $segment,
        public readonly bool $can_view_financials,
        public readonly ?array $contact_ids = null,
        public readonly bool $with_suppression = false,
    ) {}
}
