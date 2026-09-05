<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\SpamEvents;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Admin\ApproveSpamEventHandler;
use Illuminate\Http\JsonResponse;

class ApproveSpamEventAction extends BaseAction
{
    public function __construct(
        private readonly ApproveSpamEventHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $this->handler->handle($eventId, $this->getAuthenticatedUser()->getId());

        return $this->jsonResponse([
            'message' => __('Event approved and published'),
        ]);
    }
}
