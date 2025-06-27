<?php

namespace HiEvents\Http\Actions\Users;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\User\ResendEmailConfirmationHandler;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class ResendEmailConfirmationAction extends BaseAction
{
    public function __construct(
        private readonly ResendEmailConfirmationHandler $resendEmailConfirmationHandler,
    )
    {
    }

    public function __invoke(int $userId): Response
    {
        $user = $this->getAuthenticatedUser();
        $cacheKey = 'resend_email_confirmation:' . $user->getId();

        // Check if user has requested a resend within the last 30 seconds
        if (Cache::has($cacheKey)) {
            $remainingSeconds = Cache::get($cacheKey) - now()->timestamp;
            throw new TooManyRequestsHttpException($remainingSeconds, __(
                'Please wait :seconds seconds before requesting another code.', [
                'seconds' => $remainingSeconds,
            ]));
        }

        // Set the cooldown for 30 seconds
        Cache::put($cacheKey, now()->addSeconds(30)->timestamp, 30);

        $this->resendEmailConfirmationHandler->handle($user, $this->getAuthenticatedAccountId());

        return $this->noContentResponse();
    }
}
