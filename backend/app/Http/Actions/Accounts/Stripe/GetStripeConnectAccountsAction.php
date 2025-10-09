<?php

namespace HiEvents\Http\Actions\Accounts\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\Stripe\StripeConnectAccountsResponseResource;
use HiEvents\Services\Application\Handlers\Account\Payment\Stripe\GetStripeConnectAccountsHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class GetStripeConnectAccountsAction extends BaseAction
{
    public function __construct(
        private readonly GetStripeConnectAccountsHandler $getStripeConnectAccountsHandler,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class, Role::ADMIN);

        $result = $this->getStripeConnectAccountsHandler->handle($accountId);

        return $this->resourceResponse(
            resource: StripeConnectAccountsResponseResource::class,
            data: $result,
        );
    }
}
