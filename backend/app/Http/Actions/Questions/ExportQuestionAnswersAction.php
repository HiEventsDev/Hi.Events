<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Jobs\Question\ExportAnswersJob;
use HiEvents\Services\Infrastructure\Jobs\JobPollingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ExportQuestionAnswersAction extends BaseAction
{
    public function __construct(private JobPollingService $jobPollingService)
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        if ($jobUuid = $request->get('job_uuid')) {
            return $this->handleExistingJob($jobUuid, $eventId);
        }

        return $this->startNewExportJob($eventId);
    }

    private function handleExistingJob(string $jobUuid, int $eventId): JsonResponse
    {
        $filePath = "event_$eventId/answers-$jobUuid.xlsx";

        $jobStatus = $this->jobPollingService->checkJobStatus($jobUuid, $filePath);

        return $this->jsonResponse([
            'message' => $jobStatus->message,
            'status' => $jobStatus->status->name,
            'job_uuid' => $jobStatus->jobUuid,
            'download_url' => $jobStatus->downloadUrl,
        ]);
    }

    private function startNewExportJob(int $eventId): JsonResponse
    {
        $jobStatus = $this->jobPollingService->startJob(
            jobName: "Export Questions for Event #$eventId",
            jobs: [new ExportAnswersJob($eventId)]
        );

        return $this->jsonResponse([
            'message' => $jobStatus->message,
            'status' => $jobStatus->status->name,
            'job_uuid' => $jobStatus->jobUuid,
        ]);
    }
}
