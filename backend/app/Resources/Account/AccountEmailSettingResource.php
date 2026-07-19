<?php

declare(strict_types=1);

namespace HiEvents\Resources\Account;

use HiEvents\DomainObjects\AccountEmailSettingDomainObject;
use HiEvents\Resources\BaseResource;

/**
 * @mixin AccountEmailSettingDomainObject
 */
class AccountEmailSettingResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'account_id' => $this->getAccountId(),
            'smtp_enabled' => $this->getSmtpEnabled(),
            'smtp_host' => $this->getSmtpHost(),
            'smtp_port' => $this->getSmtpPort(),
            'smtp_encryption' => $this->getSmtpEncryption(),
            'smtp_username' => $this->getSmtpUsername(),
            // Never expose the password back to the client; send a boolean so the
            // frontend knows whether one is stored.
            'smtp_password_set' => $this->getSmtpPassword() !== null && $this->getSmtpPassword() !== '',
            'mail_from_address' => $this->getMailFromAddress(),
            'mail_from_name' => $this->getMailFromName(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
