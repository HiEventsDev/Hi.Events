<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertContactDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly ?string $email = null,
        public readonly ?int $contact_id = null,
        public readonly bool $restore_deleted = false,
    ) {}
}
