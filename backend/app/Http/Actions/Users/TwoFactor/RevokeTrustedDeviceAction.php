<?php

namespace HiEvents\Http\Actions\Users\TwoFactor;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\User\TwoFactor\RevokeTrustedDevicesHandler;
use Illuminate\Http\Response;

class RevokeTrustedDeviceAction extends BaseAction
{
    public function __construct(
        private readonly RevokeTrustedDevicesHandler $handler,
    ) {}

    public function __invoke(int $deviceId): Response
    {
        try {
            $this->handler->revoke($this->getAuthenticatedUser()->getId(), $deviceId);
        } catch (ResourceNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->deletedResponse();
    }
}
