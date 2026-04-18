<?php

namespace HiEvents\Http\Actions\Contacts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Contact\DeleteContactHandler;
use Illuminate\Http\Response;

class DeleteContactAction extends BaseAction
{
    public function __construct(
        private readonly DeleteContactHandler $handler,
    ) {}

    public function __invoke(int $accountId, int $contactId): Response
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $this->handler->handle($contactId, $this->getAuthenticatedAccountId());

        return $this->deletedResponse();
    }
}
