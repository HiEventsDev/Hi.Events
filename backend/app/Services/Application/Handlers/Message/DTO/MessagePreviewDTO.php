<?php

namespace HiEvents\Services\Application\Handlers\Message\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class MessagePreviewDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $html,
        public readonly string $subject,
        public readonly string $from_name,
        public readonly ?string $reply_to,
    ) {}
}
