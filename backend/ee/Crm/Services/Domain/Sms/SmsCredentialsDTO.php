<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DataTransferObjects\BaseDataObject;

class SmsCredentialsDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $account_sid,
        public readonly string $auth_token,
        public readonly ?string $messaging_service_sid = null,
    ) {}
}
