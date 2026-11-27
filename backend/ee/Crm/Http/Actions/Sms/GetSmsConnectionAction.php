<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sms;

use HiEvents\Enterprise\Crm\Resources\SmsConnectionResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\GetSmsConnectionHandler;
use Illuminate\Http\JsonResponse;

class GetSmsConnectionAction extends BaseSmsConnectionAction
{
    public function __construct(
        private readonly GetSmsConnectionHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        $connection = $this->handler->handle($organizerId);

        return $connection === null
            ? $this->jsonResponse(['data' => null])
            : $this->resourceResponse(SmsConnectionResource::class, $connection);
    }
}
