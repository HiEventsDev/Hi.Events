<?php

namespace HiEvents\Http\Actions\Accounts\Subscription;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Account\AccountSubscriptionResource;
use HiEvents\Services\Application\Handlers\Account\Subscription\GetAccountSubscriptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAccountSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly GetAccountSubscriptionHandler $handler,
    ) {}

    public function __invoke(int $accountId, Request $request): JsonResponse
    {
        $this->isAccountReadAuthorized($accountId);

        return $this->resourceResponse(
            AccountSubscriptionResource::class,
            $this->handler->handle($accountId, $request->boolean('refresh')),
        );
    }
}
