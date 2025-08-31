<?php

namespace HiEvents\Http\Actions\EmailTemplates;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\EmailTemplateNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\EmailTemplate\DeleteEmailTemplateHandler;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\DeleteEmailTemplateDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteEventEmailTemplateAction extends BaseAction
{
    public function __construct(
        private readonly DeleteEmailTemplateHandler $handler
    )
    {
    }

    public function __invoke(int $eventId, int $templateId): Response|JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->handler->handle(
                new DeleteEmailTemplateDTO(
                    id: $templateId,
                    account_id: $this->getAuthenticatedAccountId(),
                )
            );
        } catch (EmailTemplateNotFoundException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_NOT_FOUND,
            );
        }

        return $this->deletedResponse();
    }
}
