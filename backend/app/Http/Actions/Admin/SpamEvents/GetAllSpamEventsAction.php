<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Admin\SpamEvents;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Admin\AdminSpamEventResource;
use HiEvents\Services\Application\Handlers\Admin\GetAllSpamEventsForAdminHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAllSpamEventsAction extends BaseAction
{
    public function __construct(
        private readonly GetAllSpamEventsForAdminHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::SUPERADMIN);

        $spamEvents = $this->handler->handle(
            search: $request->query('search'),
            perPage: min((int) $request->query('per_page', 20), 100),
        );

        return $this->resourceResponse(
            resource: AdminSpamEventResource::class,
            data: $spamEvents,
        );
    }
}
