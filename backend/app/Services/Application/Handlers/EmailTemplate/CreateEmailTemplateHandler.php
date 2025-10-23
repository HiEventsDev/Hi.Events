<?php

namespace HiEvents\Services\Application\Handlers\EmailTemplate;

use HiEvents\DomainObjects\EmailTemplateDomainObject;
use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EmailTemplateRepositoryInterface;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\UpsertEmailTemplateDTO;
use HiEvents\Services\Domain\Email\EmailTemplateService;

class CreateEmailTemplateHandler
{
    public function __construct(
        private readonly EmailTemplateRepositoryInterface $emailTemplateRepository,
        private readonly EmailTemplateService             $emailTemplateService,
    )
    {
    }

    /**
     * @throws EmailTemplateValidationException
     * @throws ResourceConflictException
     */
    public function handle(UpsertEmailTemplateDTO $dto): EmailTemplateDomainObject
    {
        $validation = $this->emailTemplateService->validateTemplate($dto->subject, $dto->body);
        if (!$validation['valid']) {
            $exception = new EmailTemplateValidationException('Template validation failed');
            $exception->validationErrors = $validation['errors'];
            throw $exception;
        }

        // Check for existing template
        $existing = $this->emailTemplateRepository->findByTypeAndScope(
            $dto->template_type,
            $dto->account_id,
            $dto->event_id,
            $dto->organizer_id
        );

        if ($existing) {
            throw new ResourceConflictException('A template already exists for this type and scope');
        }

        // Create the template
        return $this->emailTemplateRepository->create([
            'account_id' => $dto->account_id,
            'organizer_id' => $dto->organizer_id,
            'event_id' => $dto->event_id,
            'template_type' => $dto->template_type->value,
            'subject' => $dto->subject,
            'body' => $dto->body,
            'cta' => $dto->cta,
            'engine' => $dto->engine->value,
            'is_active' => $dto->is_active,
        ]);
    }
}
