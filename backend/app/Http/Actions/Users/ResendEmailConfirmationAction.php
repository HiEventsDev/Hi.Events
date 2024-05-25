<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Handlers\User\ResendEmailConfirmationHandler;
use Illuminate\Http\Response;

class ResendEmailConfirmationAction extends BaseAction
{
    public function __construct(
            private readonly ResendEmailConfirmationHandler $resendEmailConfirmationHandler,
    )
    {
    }

    public function __invoke(int $userId): Response
    {
        $this->resendEmailConfirmationHandler->handle($this->getAuthenticatedUser());

        return $this->noContentResponse();
    }
}
