<?php

namespace TicketKitten\Http\Actions\Accounts\Stripe;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use TicketKitten\DomainObjects\AccountDomainObject;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\Exceptions\CreateStripeConnectAccountFailedException;
use TicketKitten\Exceptions\CreateStripeConnectAccountLinksFailedException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\CreateStripeConnectAccountDTO;
use TicketKitten\Resources\Account\Stripe\StripeConnectAccountResponseResource;
use TicketKitten\Service\Handler\Account\Payment\Stripe\CreateStripeConnectAccountHandler;


class CreateStripeConnectAccountAction extends BaseAction
{
    public function __construct(
        private readonly CreateStripeConnectAccountHandler $createStripeConnectAccountHandler,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class, Role::ADMIN);

        try {
            $accountResult = $this->createStripeConnectAccountHandler->handle(CreateStripeConnectAccountDTO::fromArray([
                'accountId' => $accountId,
            ]));
        } catch (CreateStripeConnectAccountLinksFailedException|CreateStripeConnectAccountFailedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->resourceResponse(
            resource: StripeConnectAccountResponseResource::class,
            data: $accountResult
        );
    }
}
