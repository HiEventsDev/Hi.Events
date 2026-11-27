<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SuppressionDetailDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $contact_id,
        public readonly string $reason,
        public readonly string $since,
        public readonly string $source_type,
        public readonly string $email,
        public readonly ?string $campaign_name,
        public readonly ?string $import_file_name,
        public readonly ?string $user_name,
        public readonly bool $provider_rejected,
        public readonly ?string $provider_rejected_at,
    ) {}
}
