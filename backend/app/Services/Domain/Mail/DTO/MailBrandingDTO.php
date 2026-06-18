<?php

namespace HiEvents\Services\Domain\Mail\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class MailBrandingDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $hideBranding,
        public readonly ?string $organizerLogoUrl,
        public readonly ?string $organizerName,
    ) {}
}
