<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Auth\Sso\GetSsoProvidersHandler;
use Illuminate\Http\JsonResponse;

class GetSsoProvidersAction extends BaseAction
{
    public function __construct(
        private readonly GetSsoProvidersHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->jsonResponse($this->handler->handle(), wrapInData: true);
    }
}
