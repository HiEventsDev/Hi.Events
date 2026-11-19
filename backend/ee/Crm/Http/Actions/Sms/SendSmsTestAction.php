<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sms;

use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Http\Request\SendSmsTestRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\SendSmsTestHandler;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SendSmsTestAction extends BaseSmsConnectionAction
{
    public function __construct(
        private readonly SendSmsTestHandler $handler,
    ) {}

    public function __invoke(SendSmsTestRequest $request, int $organizerId): Response
    {
        $this->authorizeSmsChange($organizerId);

        try {
            $this->handler->handle($organizerId, $request->validated('phone'));
        } catch (SmsProviderException $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        return $this->noContentResponse();
    }
}
