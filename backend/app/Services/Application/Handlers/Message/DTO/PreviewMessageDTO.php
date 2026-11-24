<?php

namespace HiEvents\Services\Application\Handlers\Message\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class PreviewMessageDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $event_id,
        public readonly int $user_id,
        public readonly ?string $subject,
        public readonly ?string $message,
    ) {}
}
