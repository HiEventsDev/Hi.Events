<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\EmailTemplateNotFoundException;
use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Exceptions\InvalidEmailTemplateException;
use HiEvents\Http\Resources\EmailTemplateResource;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\UpsertEmailTemplateDTO;
use HiEvents\Services\Application\Handlers\EmailTemplate\UpdateEmailTemplateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpdateEventEmailTemplateAction extends BaseEmailTemplateAction
{
    public function __construct(
        private readonly UpdateEmailTemplateHandler $handler
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $eventId, int $templateId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $this->validateUpdateEmailTemplateRequest($request);

        try {
            $cta = [
                'label' => $validated['ctaLabel'],
                'url_token' => 'order.url', // This will be determined by template type during update
            ];
            
            $template = $this->handler->handle(
                new UpsertEmailTemplateDTO(
                    account_id: $this->getAuthenticatedAccountId(),
                    template_type: EmailTemplateType::ORDER_CONFIRMATION, // This will be ignored in update
                    subject: $validated['subject'],
                    body: $validated['body'],
                    organizer_id: null,
                    event_id: $eventId,
                    id: $templateId,
                    cta: $cta,
                    is_active: $validated['isActive'] ?? true,
                )
            );
        } catch (EmailTemplateValidationException $e) {
            throw ValidationException::withMessages($e->validationErrors ?: ['body' => $e->getMessage()]);
        } catch (InvalidEmailTemplateException $e) {
            throw ValidationException::withMessages([
                'id' => $e->getMessage(),
            ]);
        } catch (EmailTemplateNotFoundException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_NOT_FOUND,
            );
        }

        return $this->resourceResponse(
            resource: EmailTemplateResource::class,
            data: $template,
        );
    }
}
