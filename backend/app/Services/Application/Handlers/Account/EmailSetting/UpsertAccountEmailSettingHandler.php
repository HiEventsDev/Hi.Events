<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Account\EmailSetting;

use HiEvents\DomainObjects\AccountEmailSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountEmailSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\EmailSetting\DTO\UpsertAccountEmailSettingDTO;

class UpsertAccountEmailSettingHandler
{
    public function __construct(
        private readonly AccountEmailSettingRepositoryInterface $emailSettingRepository,
    ) {
    }

    public function handle(UpsertAccountEmailSettingDTO $command): AccountEmailSettingDomainObject
    {
        $existing = $this->emailSettingRepository->findByAccountId($command->accountId);

        $data = [
            'account_id' => $command->accountId,
            'smtp_enabled' => $command->smtpEnabled,
            'smtp_host' => $command->smtpHost,
            'smtp_port' => $command->smtpPort,
            'smtp_encryption' => $command->smtpEncryption,
            'smtp_username' => $command->smtpUsername,
            'mail_from_address' => $command->mailFromAddress,
            'mail_from_name' => $command->mailFromName,
        ];

        // Only overwrite the password if a new one is provided.
        // An empty/null value means "leave existing password unchanged".
        if ($command->smtpPassword !== null && $command->smtpPassword !== '') {
            $data['smtp_password'] = $command->smtpPassword;
        }

        if ($existing) {
            return $this->emailSettingRepository->updateFromArray(
                id: $existing->getId(),
                attributes: $data,
            );
        }

        return $this->emailSettingRepository->create($data);
    }
}
