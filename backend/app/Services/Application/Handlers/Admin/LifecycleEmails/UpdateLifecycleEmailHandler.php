<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\LifecycleEmails;

use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\AdminLifecycleEmailDTO;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\LifecycleEmailContentDTO;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailTemplateService;

class UpdateLifecycleEmailHandler
{
    public function __construct(
        private readonly GetLifecycleEmailsHandler $getLifecycleEmailsHandler,
        private readonly LifecycleEmailTemplateService $templateService,
    ) {}

    /**
     * @throws EmailTemplateValidationException
     */
    public function handle(LifecycleEmailContentDTO $content, bool $enabled): AdminLifecycleEmailDTO
    {
        $this->templateService->save(
            nudgeType: $content->nudgeType,
            subject: $content->subject,
            body: $content->body,
            ctaLabel: $content->ctaLabel,
            enabled: $enabled,
        );

        return $this->getLifecycleEmailsHandler->handle()
            ->first(fn (AdminLifecycleEmailDTO $email) => $email->template->nudgeType === $content->nudgeType);
    }
}
