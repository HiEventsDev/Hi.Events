<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\Enterprise\Crm\Exceptions\InvalidPlaceholderException;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertAutomationRequest;
use HiEvents\Enterprise\Crm\Resources\AutomationResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\DTO\UpsertAutomationDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\UpsertAutomationHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

abstract class BaseUpsertAutomationAction extends BaseCrmAction
{
    public function __construct(
        private readonly UpsertAutomationHandler $handler,
    ) {}

    protected function upsert(UpsertAutomationRequest $request, int $organizerId, CrmAutomationTrigger $trigger, ?int $automationId, int $successStatus): JsonResponse
    {
        try {
            $automation = $this->handler->handle(new UpsertAutomationDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                trigger: $trigger,
                name: $request->validated('name'),
                subject: $request->validated('subject'),
                body: $request->validated('body'),
                segment_id: $request->validated('segment_id') === null ? null : (int) $request->validated('segment_id'),
                preset: $request->validated('preset'),
                config: $request->config($trigger),
                can_view_financials: $this->canViewFinancials(),
                automation_id: $automationId,
                preheader: $request->validated('preheader'),
            ));
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        } catch (InvalidPlaceholderException $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->resourceResponse(AutomationResource::class, $automation, $successStatus);
    }
}
