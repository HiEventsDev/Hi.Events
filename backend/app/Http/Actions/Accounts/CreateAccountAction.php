<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts;

use HiEvents\Exceptions\EmailAlreadyExists;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Http\Request\Account\CreateAccountRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Account\AccountResource;
use HiEvents\Services\Handlers\Account\CreateAccountHandler;
use HiEvents\Services\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Handlers\Auth\DTO\LoginCredentialsDTO;
use HiEvents\Services\Handlers\Auth\LoginHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateAccountAction extends BaseAuthAction
{
    public function __construct(
        private readonly CreateAccountHandler $createAccountHandler,
        private readonly LoginHandler         $loginHandler,
    )
    {
    }

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function __invoke(CreateAccountRequest $request): JsonResponse
    {
        try {
            $accountData = $this->createAccountHandler->handle(CreateAccountDTO::fromArray([
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'timezone' => $request->validated('timezone'),
                'currency_code' => $request->validated('currency_code'),
                'locale' => $request->getPreferredLanguage(),
            ]));
        } catch (EmailAlreadyExists $e) {
            throw ValidationException::withMessages([
                'email' => $e->getMessage(),
            ]);
        }

        try {
            $loginResponse = $this->loginHandler->handle(new LoginCredentialsDTO(
                email: $accountData->getEmail(),
                password: $request->validated('password'),
            ));
        } catch (UnauthorizedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        return $this->resourceResponse(
            resource: AccountResource::class,
            data: $accountData,
            statusCode: ResponseCodes::HTTP_CREATED,
            headers: [
                'X-Auth-Token' => $loginResponse->token,
            ]
        );
    }
}
