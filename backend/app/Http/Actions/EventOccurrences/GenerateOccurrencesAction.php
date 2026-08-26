<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\InvalidRecurrenceRuleException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventOccurrence\GenerateOccurrencesRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\GenerateOccurrencesDTO;
use HiEvents\Services\Application\Handlers\EventOccurrence\StartOccurrenceGenerationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class GenerateOccurrencesAction extends BaseAction
{
    public function __construct(
        private readonly StartOccurrenceGenerationHandler $handler,
    ) {}

    public function __invoke(int $eventId, GenerateOccurrencesRequest $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $jobStatus = $this->handler->handle(
                new GenerateOccurrencesDTO(
                    event_id: $eventId,
                    recurrence_rule: $request->validated('recurrence_rule'),
                )
            );
        } catch (InvalidRecurrenceRuleException $e) {
            throw ValidationException::withMessages([
                'recurrence_rule' => [$e->getMessage()],
            ]);
        }

        return $this->jsonResponse([
            'message' => $jobStatus->message,
            'status' => $jobStatus->status->name,
            'job_uuid' => $jobStatus->jobUuid,
        ], ResponseCodes::HTTP_ACCEPTED);
    }
}
