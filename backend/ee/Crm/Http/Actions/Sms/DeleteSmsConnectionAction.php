<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Sms;

use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms\DeleteSmsConnectionHandler;
use Illuminate\Http\Response;

class DeleteSmsConnectionAction extends BaseSmsConnectionAction
{
    public function __construct(
        private readonly DeleteSmsConnectionHandler $handler,
    ) {}

    public function __invoke(int $organizerId): Response
    {
        $this->authorizeCrm($organizerId);

        $this->handler->handle($organizerId);

        return $this->deletedResponse();
    }
}
