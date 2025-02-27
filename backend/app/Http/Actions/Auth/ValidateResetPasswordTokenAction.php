<?php

namespace HiEvents\Http\Actions\Auth;

use HiEvents\Exceptions\InvalidPasswordResetTokenException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Auth\ValidateResetPasswordTokenHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class ValidateResetPasswordTokenAction extends BaseAction
{
    private ValidateResetPasswordTokenHandler $validateResetPasswordTokenHandler;

    public function __construct(ValidateResetPasswordTokenHandler $validateResetPasswordTokenHandler)
    {
        $this->validateResetPasswordTokenHandler = $validateResetPasswordTokenHandler;
    }

    /**
     * @throws ResourceNotFoundException|Throwable
     */
    public function __invoke(Request $request): Response
    {
        try {
            $this->validateResetPasswordTokenHandler->handle($request->route('reset_token'));
        } catch (InvalidPasswordResetTokenException $e) {
            throw new ResourceNotFoundException($e->getMessage());
        }

        return $this->noContentResponse();
    }
}
