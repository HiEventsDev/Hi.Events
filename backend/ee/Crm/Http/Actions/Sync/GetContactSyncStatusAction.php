<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sync;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync\GetContactSyncStatusHandler;
use Illuminate\Http\JsonResponse;

class GetContactSyncStatusAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetContactSyncStatusHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->jsonResponse($this->handler->handle($organizerId), wrapInData: true);
    }
}
