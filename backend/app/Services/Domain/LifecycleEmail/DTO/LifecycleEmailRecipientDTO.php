<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class LifecycleEmailRecipientDTO extends BaseDataObject
{
    public function __construct(
        public string $firstName,
        public string $ctaUrl,
        public array $context = [],
        public ?int $accountId = null,
        public ?int $userId = null,
    ) {}
}
