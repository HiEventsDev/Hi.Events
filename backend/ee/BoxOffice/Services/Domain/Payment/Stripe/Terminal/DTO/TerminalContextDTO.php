<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\StripePlatform;
use Stripe\StripeClient;

class TerminalContextDTO extends BaseDataObject
{
    public function __construct(
        public StripeClient $client,
        public ?StripePlatform $platform,
        public ?string $stripe_account_id,
        public ?int $organizer_stripe_platform_id,
    ) {}

    public function requestOptions(): array
    {
        return $this->stripe_account_id === null ? [] : ['stripe_account' => $this->stripe_account_id];
    }
}
