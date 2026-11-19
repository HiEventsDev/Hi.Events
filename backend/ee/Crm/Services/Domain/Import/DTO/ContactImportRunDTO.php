<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Import\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\CrmImportKind;

class ContactImportRunDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly int $user_id,
        public readonly string $file_name,
        public readonly CrmImportKind $kind,
        public readonly bool $consent_attested = false,
        public readonly ?int $tag_id = null,
    ) {}
}
