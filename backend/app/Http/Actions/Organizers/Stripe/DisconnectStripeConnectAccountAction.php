<?php

namespace HiEvents\Http\Actions\Organizers\Stripe;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DisconnectStripeConnectAccountHandler;
use Symfony\Component\HttpFoundation\Response;

class DisconnectStripeConnectAccountAction extends BaseAction
{
    public function __construct(
        private readonly DisconnectStripeConnectAccountHandler $disconnectStripeConnectAccountHandler,
    ) {}

    public function __invoke(int $organizerId, string $stripeAccountId): Response
    {
        $this->isActionAuthorized($organizerId, OrganizerDomainObject::class, Role::ADMIN);

        try {
            $this->disconnectStripeConnectAccountHandler->handle(
                organizerId: $organizerId,
                accountId: $this->getAuthenticatedAccountId(),
                stripeAccountId: $stripeAccountId,
            );
        } catch (ResourceNotFoundException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_NOT_FOUND,
            );
        }

        return $this->deletedResponse();
    }
}
