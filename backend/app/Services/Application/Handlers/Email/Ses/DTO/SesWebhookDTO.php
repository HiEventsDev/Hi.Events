<?php

namespace HiEvents\Services\Application\Handlers\Email\Ses\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SesWebhookDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $payload,
    )
    {
    }
}
