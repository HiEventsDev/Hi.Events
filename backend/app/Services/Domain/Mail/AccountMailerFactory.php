<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Mail;

use HiEvents\DomainObjects\AccountEmailSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountEmailSettingRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;

/**
 * Resolves a Mailer instance for the given account.
 *
 * When the account has custom SMTP settings enabled, a dynamically-configured
 * mailer is returned. Otherwise the application-level default mailer is used,
 * which is configured via the .env MAIL_* variables.
 */
class AccountMailerFactory
{
    public function __construct(
        private readonly MailManager $mailManager,
        private readonly AccountEmailSettingRepositoryInterface $emailSettingRepository,
    ) {
    }

    public function forAccount(int $accountId): Mailer
    {
        $setting = $this->emailSettingRepository->findByAccountId($accountId);

        if ($setting && $this->isUsable($setting)) {
            return $this->buildCustomMailer($setting);
        }

        return $this->mailManager->mailer();
    }

    private function isUsable(AccountEmailSettingDomainObject $setting): bool
    {
        return $setting->getSmtpEnabled()
            && $setting->getSmtpHost() !== null
            && $setting->getSmtpPort() !== null;
    }

    private function buildCustomMailer(AccountEmailSettingDomainObject $setting): Mailer
    {
        $mailerName = 'account_smtp_' . $setting->getAccountId();

        // Register the mailer config at runtime so Laravel's MailManager can resolve it.
        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'host' => $setting->getSmtpHost(),
                'port' => $setting->getSmtpPort(),
                'encryption' => $setting->getSmtpEncryption(),
                'username' => $setting->getSmtpUsername(),
                'password' => $setting->getSmtpPassword(),
                'timeout' => null,
            ],
        ]);

        if ($setting->getMailFromAddress()) {
            config([
                "mail.from" => [
                    'address' => $setting->getMailFromAddress(),
                    'name' => $setting->getMailFromName() ?? config('mail.from.name'),
                ],
            ]);
        }

        return $this->mailManager->mailer($mailerName);
    }
}
