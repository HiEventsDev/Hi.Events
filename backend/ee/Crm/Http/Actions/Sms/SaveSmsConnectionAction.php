<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sms;

use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Http\Request\SaveSmsConnectionRequest;
use HiEvents\Enterprise\Crm\Resources\SmsConnectionResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\SaveSmsConnectionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SaveSmsConnectionAction extends BaseSmsConnectionAction
{
    public function __construct(
        private readonly SaveSmsConnectionHandler $handler,
    ) {}

    public function __invoke(SaveSmsConnectionRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeSmsChange($organizerId);

        try {
            $connection = $this->handler->handle(
                accountId: $this->getAuthenticatedAccountId(),
                organizerId: $organizerId,
                accountSid: $request->validated('account_sid'),
                authToken: $request->validated('auth_token'),
                messagingServiceSid: $request->validated('messaging_service_sid'),
                enabledCountries: $request->validated('enabled_countries'),
            );
        } catch (SmsProviderException $exception) {
            throw ValidationException::withMessages(['messaging_service_sid' => $exception->getMessage()]);
        }

        return $this->resourceResponse(SmsConnectionResource::class, $connection);
    }
}
