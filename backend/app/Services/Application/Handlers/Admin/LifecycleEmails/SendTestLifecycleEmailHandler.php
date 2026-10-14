<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\LifecycleEmails;

use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Exceptions\LifecycleEmailSendingNotConfiguredException;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\LifecycleEmailContentDTO;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailSender;

class SendTestLifecycleEmailHandler
{
    public function __construct(
        private readonly PreviewLifecycleEmailHandler $previewHandler,
        private readonly LifecycleEmailSender $sender,
    ) {}

    /**
     * @throws EmailTemplateValidationException
     * @throws LifecycleEmailSendingNotConfiguredException
     */
    public function handle(LifecycleEmailContentDTO $content, string $email, int $userId): void
    {
        if (! $this->sender->isConfigured()) {
            throw new LifecycleEmailSendingNotConfiguredException(__('No lifecycle mailer is configured, so test emails cannot be sent'));
        }

        $this->sender->send($email, $this->previewHandler->buildSampleMail($content, $userId));
    }
}
