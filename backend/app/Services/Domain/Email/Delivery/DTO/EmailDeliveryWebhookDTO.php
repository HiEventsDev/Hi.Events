<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Email\Delivery\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\EmailDeliveryProvider;

class EmailDeliveryWebhookDTO extends BaseDataObject
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public EmailDeliveryProvider $provider,
        public array $headers,
        public string $payload,
    ) {}
}
