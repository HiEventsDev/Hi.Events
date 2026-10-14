<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail;

use HiEvents\Mail\Lifecycle\LifecycleNudgeMail;
use HiEvents\Services\Domain\Email\Delivery\EmailProviderMessageIdResolver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Mail\Factory as MailFactory;

class LifecycleEmailSender
{
    public function __construct(
        private readonly MailFactory $mailFactory,
        private readonly ConfigRepository $config,
        private readonly LifecycleEmailAvailability $availability,
        private readonly EmailProviderMessageIdResolver $providerMessageIdResolver,
    ) {}

    public function isConfigured(): bool
    {
        return $this->availability->isAvailable() && $this->mailerName() !== null;
    }

    public function send(string $email, LifecycleNudgeMail $mail): ?string
    {
        $sentMessage = $this->mailFactory->mailer($this->mailerName())->to($email)->send($mail);

        return $sentMessage === null
            ? null
            : $this->providerMessageIdResolver->resolve($sentMessage->getSymfonySentMessage());
    }

    private function mailerName(): ?string
    {
        return $this->config->get('mail.lifecycle.mailer') ?: null;
    }
}
