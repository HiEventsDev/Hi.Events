<?php

declare(strict_types=1);

namespace HiEvents\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFailedJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = json_decode($this->payload, true);
        $jobName = $payload['displayName'] ?? 'Unknown';

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'job_name' => class_basename($jobName),
            'job_name_full' => $jobName,
            'payload' => $this->payload,
            'exception_summary' => $this->getExceptionSummary(),
            'exception' => $this->exception,
            'failed_at' => $this->failed_at,
        ];
    }

    private function getExceptionSummary(): string
    {
        $lines = explode("\n", $this->exception);
        return $lines[0] ?? 'Unknown error';
    }
}
