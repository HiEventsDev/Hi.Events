<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\LifecycleEmails;

use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Mail\Lifecycle\LifecycleNudgeMail;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\LifecycleEmailContentDTO;
use HiEvents\Services\Application\Handlers\Admin\LifecycleEmails\DTO\LifecycleEmailPreviewDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailRecipientDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailTemplateService;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\LifecycleNudgeRegistry;

class PreviewLifecycleEmailHandler
{
    public function __construct(
        private readonly LifecycleEmailTemplateService $templateService,
        private readonly LifecycleNudgeRegistry $nudgeRegistry,
    ) {}

    /**
     * @throws EmailTemplateValidationException
     */
    public function handle(LifecycleEmailContentDTO $content): LifecycleEmailPreviewDTO
    {
        $mail = $this->buildSampleMail($content);

        return new LifecycleEmailPreviewDTO(
            subject: $mail->envelope()->subject,
            html: $mail->render(),
        );
    }

    /**
     * @throws EmailTemplateValidationException
     */
    public function buildSampleMail(LifecycleEmailContentDTO $content, ?int $userId = null): LifecycleNudgeMail
    {
        $sample = $this->nudgeRegistry->for($content->nudgeType)->sampleRecipient();

        return $this->templateService->buildMail(
            new LifecycleEmailTemplateDTO(
                nudgeType: $content->nudgeType,
                subject: $content->subject,
                body: $content->body,
                ctaLabel: $content->ctaLabel,
            ),
            new LifecycleEmailRecipientDTO(
                firstName: $sample->firstName,
                ctaUrl: $sample->ctaUrl,
                context: $sample->context,
                userId: $userId,
            ),
        );
    }
}
