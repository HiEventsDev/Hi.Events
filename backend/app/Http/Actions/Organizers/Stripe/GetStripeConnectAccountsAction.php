<?php

namespace HiEvents\Http\Actions\Organizers\Stripe;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Resources\Organizer\Stripe\OrganizerStripeConnectAccountsResponseResource;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\GetStripeConnectAccountsHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[RequiresPermission(Permission::MANAGE_PAYMENTS)]
class GetStripeConnectAccountsAction extends BaseAction
{
    public function __construct(
        private readonly GetStripeConnectAccountsHandler $getStripeConnectAccountsHandler,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(int $organizerId): JsonResponse
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class);

        try {
            $result = $this->getStripeConnectAccountsHandler->handle($organizerId, $this->getAuthenticatedAccountId());
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->resourceResponse(
            resource: OrganizerStripeConnectAccountsResponseResource::class,
            data: $result,
        );
    }
}
