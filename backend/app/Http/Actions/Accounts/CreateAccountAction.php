<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Accounts;

use HiEvents\Exceptions\EmailAlreadyExists;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Actions\Auth\BaseAuthAction;
use HiEvents\Http\Request\Account\CreateAccountRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Account\AccountResource;
use HiEvents\Services\Application\Handlers\Account\CreateAccountHandler;
use HiEvents\Services\Application\Handlers\Account\DTO\CreateAccountDTO;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountConfigurationDoesNotExist;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountRegistrationDisabledException;
use HiEvents\Services\Application\Handlers\Auth\DTO\LoginCredentialsDTO;
use HiEvents\Services\Application\Handlers\Auth\LoginHandler;
use HiEvents\Services\Application\Locale\LocaleService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateAccountAction extends BaseAuthAction
{
    public function __construct(
        private readonly CreateAccountHandler $createAccountHandler,
        private readonly LoginHandler         $loginHandler,
        private readonly LocaleService        $localeService,
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
                'locale' => $request->has('locale')
                    ? $request->validated('locale')
                    : $this->localeService->getLocaleOrDefault($request->getPreferredLanguage()),
                'invite_token' => $request->validated('invite_token'),
            ]));
        } catch (EmailAlreadyExists $e) {
            throw ValidationException::withMessages([
                'email' => $e->getMessage(),
            ]);
        } catch (DecryptException $e) {
            throw ValidationException::withMessages([
                'invite_token' => __('Invalid invite token'),
            ]);
        } catch (AccountRegistrationDisabledException) {
            return $this->errorResponse(
                message: __('Account registration is disabled'),
                statusCode: ResponseCodes::HTTP_FORBIDDEN,
            );
        } catch (AccountConfigurationDoesNotExist $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        try {
            $loginResponse = $this->loginHandler->handle(new LoginCredentialsDTO(
                email: $accountData->getEmail(),
                password: $request->validated('password'),
                accountId: $accountData->getId(),
            ));
        } catch (UnauthorizedException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: ResponseCodes::HTTP_UNAUTHORIZED,
            );
        }

        return $this->addTokenToResponse($this->resourceResponse(
            resource: AccountResource::class,
            data: $accountData,
            statusCode: ResponseCodes::HTTP_CREATED,
        ), $loginResponse->token);
    }
}
