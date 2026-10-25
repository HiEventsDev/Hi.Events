<?php

namespace HiEvents\Http\Actions\Accounts\Subscription;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\SubscriptionBillingInterval;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\Account\CreateSubscriptionCheckoutSessionRequest;
use HiEvents\Services\Application\Handlers\Account\Subscription\CreateSubscriptionCheckoutSessionHandler;
use HiEvents\Services\Application\Handlers\Account\Subscription\DTO\CreateSubscriptionCheckoutSessionDTO;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class CreateSubscriptionCheckoutSessionAction extends BaseAction
{
    public function __construct(
        private readonly CreateSubscriptionCheckoutSessionHandler $handler,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(int $accountId, CreateSubscriptionCheckoutSessionRequest $request): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        if ($this->isImpersonating()) {
            return $this->impersonationForbiddenResponse(__('You cannot start a subscription while impersonating a user.'));
        }

        try {
            $url = $this->handler->handle(new CreateSubscriptionCheckoutSessionDTO(
                accountId: $accountId,
                interval: SubscriptionBillingInterval::from($request->validated('interval')),
            ));
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_CONFLICT,
            );
        } catch (StripeClientConfigurationException|ApiErrorException $exception) {
            $this->logger->error('Failed to create a subscription checkout session', [
                'account_id' => $accountId,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse(
                message: __('We couldn\'t open the payment page. Please try again later.'),
                statusCode: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $this->jsonResponse(['url' => $url]);
    }
}
