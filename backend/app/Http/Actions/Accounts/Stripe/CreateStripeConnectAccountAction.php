<?php

namespace HiEvents\Http\Actions\Accounts\Stripe;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Exceptions\CreateStripeConnectAccountFailedException;
use HiEvents\Exceptions\CreateStripeConnectAccountLinksFailedException;
use HiEvents\Exceptions\SaasModeEnabledException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\Stripe\StripeConnectAccountResponseResource;
use HiEvents\Services\Handlers\Account\Payment\Stripe\CreateStripeConnectAccountHandler;
use HiEvents\Services\Handlers\Account\Payment\Stripe\DTO\CreateStripeConnectAccountDTO;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        } catch (SaasModeEnabledException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_FORBIDDEN
            );
        }

        return $this->resourceResponse(
            resource: StripeConnectAccountResponseResource::class,
            data: $accountResult
        );
    }
}
