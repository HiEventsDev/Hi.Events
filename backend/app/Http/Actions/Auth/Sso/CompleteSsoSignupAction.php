<?php

namespace HiEvents\Http\Actions\Auth\Sso;

use HiEvents\Exceptions\SsoException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Http\Request\Auth\Sso\CompleteSsoSignupRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountConfigurationDoesNotExist;
use HiEvents\Services\Application\Handlers\Account\Exceptions\AccountRegistrationDisabledException;
use HiEvents\Services\Application\Handlers\Auth\Sso\CompleteSsoSignupHandler;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\CompleteSsoSignupDTO;
use HiEvents\Services\Domain\Account\AttributionDataSanitizer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompleteSsoSignupAction extends BaseSsoAction
{
    public function __construct(
        private readonly CompleteSsoSignupHandler $handler,
        private readonly AttributionDataSanitizer $attributionDataSanitizer,
    ) {}

    /**
     * @throws Throwable
     */
    public function __invoke(CompleteSsoSignupRequest $request): JsonResponse
    {
        try {
            $loginResponse = $this->handler->handle(new CompleteSsoSignupDTO(
                signupTicket: $request->validated('signup_ticket'),
                binding: $request->validated('binding'),
                firstName: $request->validated('first_name'),
                lastName: $request->validated('last_name'),
                timezone: $request->validated('timezone'),
                currencyCode: $request->validated('currency_code'),
                locale: $request->validated('locale'),
                marketingOptIn: (bool) $request->validated('marketing_opt_in'),
                emailCode: $request->validated('email_code'),
                inviteToken: $request->validated('invite_token'),
                attribution: $this->attributionDataSanitizer->fromValidated($request->validated()),
            ));
        } catch (SsoException $exception) {
            return $this->ssoErrorResponse($exception);
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'invite_token' => __('Invalid invite token'),
            ]);
        } catch (AccountRegistrationDisabledException) {
            return $this->errorResponse(__('Account registration is disabled'), ResponseCodes::HTTP_FORBIDDEN);
        } catch (AccountConfigurationDoesNotExist $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_INTERNAL_SERVER_ERROR);
        } catch (UnauthorizedException $exception) {
            return $this->errorResponse($exception->getMessage(), ResponseCodes::HTTP_UNAUTHORIZED);
        }

        return $this->respondWithToken(
            token: $loginResponse->token,
            accounts: $loginResponse->accounts,
            user: $loginResponse->user,
        );
    }
}
