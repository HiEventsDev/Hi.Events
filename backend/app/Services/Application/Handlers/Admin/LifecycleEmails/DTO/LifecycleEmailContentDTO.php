<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\LifecycleNudgeType;

class LifecycleEmailContentDTO extends BaseDataObject
{
    public function __construct(
        public LifecycleNudgeType $nudgeType,
        public string $subject,
        public string $body,
        public string $ctaLabel,
    ) {}
}
