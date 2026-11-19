<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UnsubscribeStatusDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $organizer_name,
        public readonly ?string $email,
        public readonly bool $is_unsubscribed,
        public readonly bool $can_resubscribe,
        public readonly string $channel,
        public readonly ?string $phone = null,
    ) {}
}
