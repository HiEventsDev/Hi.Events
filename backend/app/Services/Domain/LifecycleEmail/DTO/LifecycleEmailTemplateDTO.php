<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;

class LifecycleEmailTemplateDTO extends BaseDataObject
{
    public function __construct(
        public LifecycleNudgeType $nudgeType,
        public string $subject,
        public string $body,
        public string $ctaLabel,
        public ?string $enabledAt = null,
        public bool $isCustomised = false,
    ) {}
}
