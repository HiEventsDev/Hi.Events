<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Http\Resources\EmailTemplateResource;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\EmailTemplate\CreateEmailTemplateHandler;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\UpsertEmailTemplateDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreateEventEmailTemplateAction extends BaseEmailTemplateAction
{
    public function __construct(
        private readonly CreateEmailTemplateHandler $handler
    )
    {
    }

    /**
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $this->validateEmailTemplateRequest($request);

        try {
            $cta = [
                'label' => $validated['ctaLabel'],
                'url_token' => $validated['template_type'] === 'order_confirmation' ? 'order.url' : 'ticket.url',
            ];
            
            $template = $this->handler->handle(
                new UpsertEmailTemplateDTO(
                    account_id: $this->getAuthenticatedAccountId(),
                    template_type: EmailTemplateType::from($validated['template_type']),
                    subject: $validated['subject'],
                    body: $validated['body'],
                    organizer_id: null,
                    event_id: $eventId,
                    cta: $cta,
                    is_active: $validated['isActive'] ?? true,
                )
            );
        } catch (EmailTemplateValidationException $e) {
            throw ValidationException::withMessages($e->validationErrors ?: ['body' => $e->getMessage()]);
        } catch (ResourceConflictException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_CONFLICT,
            );
        }

        return $this->resourceResponse(
            resource: EmailTemplateResource::class,
            data: $template,
            statusCode: ResponseCodes::HTTP_CREATED
        );
    }
}
