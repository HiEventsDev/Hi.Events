<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ClearBounceDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $organizer_id,
        public readonly int $contact_id,
        public readonly int $contact_email_id,
        public readonly int $user_id,
    ) {}
}
