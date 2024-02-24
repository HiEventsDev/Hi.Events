<?php

namespace HiEvents\Http\Actions\Accounts\Stripe;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\CreateStripeConnectAccountFailedException;
use HiEvents\Exceptions\CreateStripeConnectAccountLinksFailedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DataTransferObjects\CreateStripeConnectAccountDTO;
use HiEvents\Resources\Account\Stripe\StripeConnectAccountResponseResource;
use HiEvents\Service\Handler\Account\Payment\Stripe\CreateStripeConnectAccountHandler;


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
