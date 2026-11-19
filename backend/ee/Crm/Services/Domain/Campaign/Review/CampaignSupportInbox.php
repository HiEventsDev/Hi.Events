<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review;

use Illuminate\Config\Repository;

class CampaignSupportInbox
{
    private const DOMAINS_RESERVED_FOR_EXAMPLES = ['example.com', 'example.net', 'example.org'];

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function address(): ?string
    {
        $address = trim((string) $this->config->get('app.platform_support_email'));
        $domain = strtolower(substr((string) strrchr($address, '@'), 1));

        return $domain === '' || in_array($domain, self::DOMAINS_RESERVED_FOR_EXAMPLES, true) ? null : $address;
    }
}
