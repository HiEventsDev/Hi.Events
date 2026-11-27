<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sms;

use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Http\Request\SmsCredentialsRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\ListSmsSendersHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsSenderDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ListSmsSendersAction extends BaseSmsConnectionAction
{
    public function __construct(
        private readonly ListSmsSendersHandler $handler,
    ) {}

    public function __invoke(SmsCredentialsRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeSmsChange($organizerId);

        try {
            $senders = $this->handler->handle($organizerId, $request->validated('account_sid'), $request->validated('auth_token'));
        } catch (SmsProviderException $exception) {
            throw ValidationException::withMessages(['auth_token' => $exception->getMessage()]);
        }

        return $this->jsonResponse([
            'data' => array_map(static fn (SmsSenderDTO $sender) => ['sid' => $sender->sid, 'name' => $sender->name], $senders),
        ]);
    }
}
