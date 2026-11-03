<?php

namespace HiEvents\Services\Application\Handlers\EmailTemplate;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\UpsertEmailTemplateDTO;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;

class CreateEmailTemplateHandler
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
        private readonly EmailTemplateService $emailTemplateService,
        private readonly HtmlPurifierService $purifier,
        private readonly EventSpamCheckDispatchService $eventSpamCheckDispatchService,
    ) {}

    /**
     * @throws EmailTemplateValidationException
     * @throws ResourceConflictException
     */
    public function handle(UpsertEmailTemplateDTO $dto): EmailTemplateDomainObject
    {
        $validation = $this->emailTemplateService->validateTemplate($dto->subject, $dto->body);
        if (! $validation['valid']) {
            $exception = new EmailTemplateValidationException('Template validation failed');
            $exception->validationErrors = $validation['errors'];
            throw $exception;
        }

        $existing = $this->emailTemplateRepository->findByTypeAndScope(
            $dto->template_type,
            $dto->account_id,
            $dto->event_id,
            $dto->organizer_id
        );

        if ($existing) {
            throw new ResourceConflictException(__('A template already exists for this type and scope'));
        }

        $template = $this->emailTemplateRepository->create([
            'account_id' => $dto->account_id,
            'organizer_id' => $dto->organizer_id,
            'event_id' => $dto->event_id,
            'template_type' => $dto->template_type->value,
            'subject' => $dto->subject,
            'body' => $this->purifier->purify($dto->body),
            'cta' => $dto->cta,
            'engine' => $dto->engine->value,
            'is_active' => $dto->is_active,
        ]);

        $this->eventSpamCheckDispatchService->dispatchForEmailTemplate($template);

        return $template;
    }
}
