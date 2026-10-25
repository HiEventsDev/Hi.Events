<?php

namespace HiEvents\Http\Actions\Accounts\Subscription;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Services\Application\Handlers\Account\Subscription\CreateSubscriptionPortalSessionHandler;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class CreateSubscriptionPortalSessionAction extends BaseAction
{
    public function __construct(
        private readonly CreateSubscriptionPortalSessionHandler $handler,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        if ($this->isImpersonating()) {
            return $this->impersonationForbiddenResponse(__('You cannot manage a subscription while impersonating a user.'));
        }

        try {
            $url = $this->handler->handle($accountId);
        } catch (StripeClientConfigurationException|ApiErrorException $exception) {
            $this->logger->error('Failed to create a subscription portal session', [
                'account_id' => $accountId,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse(
                message: __('We couldn\'t open the billing portal. Please try again later.'),
                statusCode: Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $this->jsonResponse(['url' => $url]);
    }
}
