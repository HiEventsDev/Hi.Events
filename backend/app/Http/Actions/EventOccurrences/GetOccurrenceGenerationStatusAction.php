<?php

namespace HiEvents\Http\Actions\EventOccurrences;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Jobs\Occurrence\GenerateOccurrencesJob;
use HiEvents\Services\Infrastructure\Jobs\JobPollingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOccurrenceGenerationStatusAction extends BaseAction
{
    public function __construct(
        private readonly JobPollingService $jobPollingService,
    ) {}

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $jobStatus = $this->jobPollingService->checkJobStatus(
            jobUuid: (string) $request->query('job_uuid'),
            expectedName: GenerateOccurrencesJob::batchName($eventId),
        );

        return $this->jsonResponse([
            'message' => $jobStatus->message,
            'status' => $jobStatus->status->name,
            'job_uuid' => $jobStatus->jobUuid,
        ]);
    }
}
