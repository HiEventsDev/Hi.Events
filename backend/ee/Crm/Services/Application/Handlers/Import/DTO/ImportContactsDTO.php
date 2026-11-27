<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ImportContactsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly int $user_id,
        public readonly string $file_path,
        public readonly string $file_name,
        public readonly bool $consent_attested,
        public readonly ?string $tag_name,
    ) {}
}
