<?php

namespace TicketKitten\Http\Actions\Auth;

use Illuminate\Http\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\Request\Auth\ForgotPasswordRequest;
use TicketKitten\Service\Handler\Auth\ForgotPasswordHandler;

class ForgotPasswordAction extends BaseAction
{
    private ForgotPasswordHandler $forgotPasswordHandler;

    public function __construct(ForgotPasswordHandler $forgotPasswordHandler)
    {
        $this->forgotPasswordHandler = $forgotPasswordHandler;
    }

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->forgotPasswordHandler->handle($request->validated('email'));
        } catch (ResourceNotFoundException) {
            // swallow the exception to prevent leaking whether an email address is known
        }

        return $this->jsonResponse(
            data: [
                'message' => 'If the email address is known, an email has been sent with further instructions.',
            ]
        );
    }
}
