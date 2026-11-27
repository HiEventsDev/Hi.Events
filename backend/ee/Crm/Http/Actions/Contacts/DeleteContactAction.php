<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DeleteContactHandler;
use Illuminate\Http\Response;

class DeleteContactAction extends BaseCrmAction
{
    public function __construct(
        private readonly DeleteContactHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $contactId): Response
    {
        $this->authorizeCrm($organizerId);

        $this->handler->handle($organizerId, $contactId);

        return $this->deletedResponse();
    }
}
