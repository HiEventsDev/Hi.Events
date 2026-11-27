<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\AutomationResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\SetAutomationEnabledHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

abstract class BaseSetAutomationEnabledAction extends BaseCrmAction
{
    public function __construct(
        private readonly SetAutomationEnabledHandler $handler,
    ) {}

    protected function setEnabled(int $organizerId, int $automationId, bool $isEnabled): JsonResponse
    {
        try {
            $automation = $this->handler->handle($organizerId, $automationId, $isEnabled, $this->canViewFinancials());
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(AutomationResource::class, $automation);
    }
}
