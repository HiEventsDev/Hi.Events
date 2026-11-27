<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\PreviewAutomationRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\SendTestAutomationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SendTestAutomationAction extends BaseCrmAction
{
    public function __construct(
        private readonly SendTestAutomationHandler $handler,
    ) {}

    public function __invoke(PreviewAutomationRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        $email = $this->getAuthenticatedUser()->getEmail();

        try {
            $this->handler->handle($this->previewFrom($request, $organizerId), $email);
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        } catch (CampaignNotSendableException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonResponse(['message' => __('Test email sent to :email', ['email' => $email])]);
    }
}
