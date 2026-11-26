<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Bulk\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;

class CreateBulkOperationDTO extends BaseDataObject
{
    /**
     * @param  int[]|null  $contact_ids  null selects every contact matching the query and segment
     * @param  int[]  $tag_ids
     */
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly int $user_id,
        public readonly CrmBulkAction $action,
        public readonly ?array $contact_ids,
        public readonly ?string $query,
        public readonly SegmentReferenceDTO $segment,
        public readonly bool $can_view_financials,
        public readonly array $tag_ids = [],
        public readonly ?int $confirmation_count = null,
    ) {}
}
