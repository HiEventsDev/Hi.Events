<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class LifecycleEmailPreviewDTO extends BaseDataObject
{
    public function __construct(
        public string $subject,
        public string $html,
    ) {}
}
