<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Public;

use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\ConfirmSubscriptionHandler;
use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;

class ConfirmSubscriptionPublicAction extends BaseAction
{
    public function __construct(
        private readonly ConfirmSubscriptionHandler $handler,
    ) {}

    public function __invoke(string $token): JsonResponse
    {
        return $this->jsonResponse($this->handler->handle($token)->toArray(), wrapInData: true);
    }
}
