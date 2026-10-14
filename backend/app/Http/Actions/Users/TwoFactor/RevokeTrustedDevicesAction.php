<?php

namespace HiEvents\Http\Actions\Users\TwoFactor;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\User\TwoFactor\RevokeTrustedDevicesHandler;
use Illuminate\Http\Response;

class RevokeTrustedDevicesAction extends BaseAction
{
    public function __construct(
        private readonly RevokeTrustedDevicesHandler $handler,
    ) {}

    public function __invoke(): Response
    {
        $this->handler->revokeAll($this->getAuthenticatedUser()->getId());

        return $this->deletedResponse();
    }
}
