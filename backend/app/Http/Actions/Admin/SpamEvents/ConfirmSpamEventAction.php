<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\SpamEvents;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\ConfirmSpamEventHandler;
use Illuminate\Http\JsonResponse;

class ConfirmSpamEventAction extends BaseAction
{
    public function __construct(
        private readonly ConfirmSpamEventHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->requireSuperAdmin();

        $this->handler->handle($eventId, $this->getAuthenticatedUser()->getId());

        return $this->jsonResponse([
            'message' => __('Event confirmed as spam'),
        ]);
    }
}
