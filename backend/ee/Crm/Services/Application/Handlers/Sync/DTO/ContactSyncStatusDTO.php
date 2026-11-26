<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ContactSyncStatusDTO extends BaseDataObject
{
    public const NOT_STARTED = 'NOT_STARTED';

    public const SYNCING = 'SYNCING';

    public const READY = 'READY';

    public const FAILED = 'FAILED';

    public function __construct(
        public readonly string $status,
        public readonly ?string $synced_at,
        public readonly int $contacts_count,
        public readonly int $mailable_count,
        public readonly int $progress_percent,
    ) {}
}
