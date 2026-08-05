<?php

namespace HiEvents\Http\Actions\Organizers\Stripe;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Organizer\Stripe\OrganizerStripeConnectAccountsResponseResource;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\GetStripeConnectAccountsHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class, Role::ADMIN);

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
