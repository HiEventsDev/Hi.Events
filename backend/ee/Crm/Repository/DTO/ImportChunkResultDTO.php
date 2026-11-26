<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ImportChunkResultDTO extends BaseDataObject
{
    /**
     * @param  int[]  $contact_ids
     */
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $previously_deleted,
        public readonly array $contact_ids,
        public readonly int $already_suppressed = 0,
        public readonly int $bounced_secondary_address = 0,
    ) {}
}
