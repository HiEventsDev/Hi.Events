<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Email\Delivery\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\EmailBounceType;
use HiEvents\DomainObjects\Enums\EmailDeliveryEventType;

class EmailDeliveryEventDTO extends BaseDataObject
{
    public function __construct(
        public string $providerMessageId,
        public EmailDeliveryEventType $type,
        public string $occurredAt,
        public ?EmailBounceType $bounceType = null,
    ) {}
}
