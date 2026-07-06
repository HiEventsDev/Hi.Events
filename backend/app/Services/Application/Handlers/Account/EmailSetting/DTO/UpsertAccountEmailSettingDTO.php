<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Account\EmailSetting\DTO;

class UpsertAccountEmailSettingDTO
{
    public function __construct(
        public readonly int $accountId,
        public readonly bool $smtpEnabled,
        public readonly ?string $smtpHost,
        public readonly ?int $smtpPort,
        public readonly ?string $smtpEncryption,
        public readonly ?string $smtpUsername,
        public readonly ?string $smtpPassword,
        public readonly ?string $mailFromAddress,
        public readonly ?string $mailFromName,
    ) {
    }
}
