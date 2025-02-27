<?php

namespace HiEvents\Services\Application\Handlers\Webhook\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Status\WebhookStatus;

class CreateWebhookDTO extends BaseDTO
{
    public function __construct(
        public string        $url,
        public array         $eventTypes,
        public int           $eventId,
        public int           $userId,
        public int           $accountId,
        public WebhookStatus $status,
    )
    {
    }
}
