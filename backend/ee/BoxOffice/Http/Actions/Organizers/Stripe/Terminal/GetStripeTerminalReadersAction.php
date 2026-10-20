<?php

namespace HiEvents\Enterprise\BoxOffice\Http\Actions\Organizers\Stripe\Terminal;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\BoxOffice\Resources\Organizer\Stripe\StripeTerminalReadersResponseResource;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal\GetStripeTerminalReadersHandler;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Http\JsonResponse;

#[RequiresPermission(Permission::MANAGE_PAYMENTS)]
class GetStripeTerminalReadersAction extends BaseAction
{
    public function __construct(
        private readonly GetStripeTerminalReadersHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        return $this->resourceResponse(
            resource: StripeTerminalReadersResponseResource::class,
            data: $this->handler->handle($organizerId, $this->getAuthenticatedAccountId()),
        );
    }
}
