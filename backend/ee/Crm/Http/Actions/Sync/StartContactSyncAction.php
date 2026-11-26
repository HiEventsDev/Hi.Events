<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sync;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync\StartContactSyncHandler;
use Illuminate\Http\JsonResponse;

class StartContactSyncAction extends BaseCrmAction
{
    public function __construct(
        private readonly StartContactSyncHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        return $this->jsonResponse(
            data: $this->handler->handle($this->getAuthenticatedAccountId(), $organizerId),
            wrapInData: true,
        );
    }
}
