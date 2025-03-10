<?php

namespace HiEvents\Services\Infrastructure\Jobs\DTO;

use HiEvents\Services\Infrastructure\Jobs\Enum\JobStatusEnum;

class JobPollingResultDTO
{
    public function __construct(
        public JobStatusEnum $status,
        public string        $message,
        public ?string       $jobUuid = null,
        public ?string       $downloadUrl = null,
    )
    {
    }
}
