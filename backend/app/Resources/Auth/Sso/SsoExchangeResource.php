<?php

namespace HiEvents\Resources\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoExchangeStatus;
use HiEvents\Resources\Account\AccountResource;
use HiEvents\Resources\User\UserResource;
use HiEvents\Services\Application\Handlers\Auth\Sso\DTO\SsoExchangeResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SsoExchangeResult
 */
class SsoExchangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $loginResponse = $this->loginResponse;

        return [
            /** @var 'AUTHENTICATED'|'TWO_FACTOR_REQUIRED'|'CHOOSE_ACCOUNT'|'SIGNUP_REQUIRED'|'LINKED'|'REAUTHENTICATED'|'TESTED' */
            'status' => $this->status->value,
            'provider_name' => $this->providerName,
            'return_path' => $this->returnPath,
            $this->mergeWhen($this->status === SsoExchangeStatus::AUTHENTICATED, fn () => [
                'token' => $loginResponse?->token,
                'user' => new UserResource($loginResponse?->user),
            ]),
            $this->mergeWhen(in_array($this->status, [SsoExchangeStatus::AUTHENTICATED, SsoExchangeStatus::CHOOSE_ACCOUNT], true), fn () => [
                'accounts' => AccountResource::collection($loginResponse?->accounts ?? collect()),
            ]),
            $this->mergeWhen($this->status === SsoExchangeStatus::TWO_FACTOR_REQUIRED, fn () => [
                'two_factor_challenge_token' => $loginResponse?->twoFactorChallengeToken,
            ]),
            $this->mergeWhen($this->status === SsoExchangeStatus::CHOOSE_ACCOUNT, fn () => [
                'login_ticket' => $this->loginTicket,
            ]),
            $this->mergeWhen($this->status === SsoExchangeStatus::SIGNUP_REQUIRED, fn () => [
                'signup' => [
                    'ticket' => $this->signupTicket,
                    'email' => $this->email,
                    'email_verified' => $this->emailVerified,
                    'first_name' => $this->firstName,
                    'last_name' => $this->lastName,
                ],
            ]),
            $this->mergeWhen($this->status === SsoExchangeStatus::REAUTHENTICATED, fn () => [
                'reauth_proof' => $this->reauthProof,
            ]),
            $this->mergeWhen($this->status === SsoExchangeStatus::TESTED, fn () => [
                'test' => $this->testResult,
            ]),
        ];
    }
}
