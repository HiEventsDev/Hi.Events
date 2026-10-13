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
use HiEvents\Services\Infrastructure\Turnstile\TurnstileVerifier;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateAccountAction extends BaseAuthAction
{
    private const MAX_UTM_RAW_PARAMS = 20;

    private const MAX_UTM_RAW_LENGTH = 255;

    public function __construct(
        private readonly CreateAccountHandler $createAccountHandler,
        private readonly LoginHandler $loginHandler,
        private readonly LocaleService $localeService,
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {}

    /**
     * @throws Throwable
     * @throws ValidationException
     */
    public function __invoke(CreateAccountRequest $request): JsonResponse
    {
        if (! $this->turnstileVerifier->verify($request->validated('turnstile_token'), $this->getClientIp($request))) {
            throw ValidationException::withMessages([
                'turnstile_token' => __('Please complete the security check and try again.'),
            ]);
        }

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
                'marketing_opt_in' => (bool) $request->validated('marketing_opt_in'),
                'utm_source' => $request->validated('utm_source'),
                'utm_medium' => $request->validated('utm_medium'),
                'utm_campaign' => $request->validated('utm_campaign'),
                'utm_term' => $request->validated('utm_term'),
                'utm_content' => $request->validated('utm_content'),
                'referrer_url' => $request->validated('referrer_url'),
                'landing_page' => $request->validated('landing_page'),
                'gclid' => $request->validated('gclid'),
                'fbclid' => $request->validated('fbclid'),
                'utm_raw' => $this->sanitizeUtmRaw($request->validated('utm_raw')),
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

    private function sanitizeUtmRaw(?array $utmRaw): ?array
    {
        if ($utmRaw === null) {
            return null;
        }

        $sanitized = collect($utmRaw)
            ->filter(fn (mixed $value, mixed $key) => is_string($key) && is_scalar($value))
            ->take(self::MAX_UTM_RAW_PARAMS)
            ->mapWithKeys(fn (mixed $value, string $key) => [
                mb_substr($key, 0, self::MAX_UTM_RAW_LENGTH) => mb_substr((string) $value, 0, self::MAX_UTM_RAW_LENGTH),
            ])
            ->all();

        return $sanitized === [] ? null : $sanitized;
    }
}
