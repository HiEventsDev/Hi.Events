<?php

namespace HiEvents\Services\Domain\Email\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class RenderedEmailTemplateDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
        public readonly ?array $cta = null,
    )
    {
    }
}