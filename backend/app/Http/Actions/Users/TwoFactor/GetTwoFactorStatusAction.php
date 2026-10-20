<?php

namespace HiEvents\Http\Actions\Users\TwoFactor;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\User\TwoFactor\TwoFactorStatusResource;
use HiEvents\Services\Application\Handlers\User\TwoFactor\GetTwoFactorStatusHandler;
use Illuminate\Http\JsonResponse;

class GetTwoFactorStatusAction extends BaseAction
{
    public function __construct(
        private readonly GetTwoFactorStatusHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->resourceResponse(
            resource: TwoFactorStatusResource::class,
            data: $this->handler->handle($this->getAuthenticatedUser()->getId()),
        );
    }
}
