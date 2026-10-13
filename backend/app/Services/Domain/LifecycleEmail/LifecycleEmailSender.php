<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail;

use HiEvents\Mail\Lifecycle\LifecycleNudgeMail;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Mail\Factory as MailFactory;

class LifecycleEmailSender
{
    public function __construct(
        private readonly MailFactory $mailFactory,
        private readonly ConfigRepository $config,
        private readonly LifecycleEmailAvailability $availability,
    ) {}

    public function isConfigured(): bool
    {
        return $this->availability->isAvailable() && $this->mailerName() !== null;
    }

    public function send(string $email, LifecycleNudgeMail $mail): void
    {
        $this->mailFactory->mailer($this->mailerName())->to($email)->send($mail);
    }

    private function mailerName(): ?string
    {
        return $this->config->get('mail.lifecycle.mailer') ?: null;
    }
}
