<?php

namespace HiEvents\Services\Domain\Mail\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class MailBrandingDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $hideHiEventsBranding,
        public readonly bool $showHiEventsPromo,
        public readonly string $promoUrl,
        public readonly ?string $organizerName = null,
        public readonly ?string $organizerLogoUrl = null,
        public readonly ?string $organizerUrl = null,
        public readonly ?string $senderName = null,
        public readonly ?string $buttonColor = null,
        public readonly ?string $buttonTextColor = null,
        public readonly ?string $linkColor = null,
    ) {}
}
