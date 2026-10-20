<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\AccountSsoConnectionHandler;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Throwable;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class DeleteAccountSsoConnectionAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly AccountSsoConnectionHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function __invoke(int $accountId): Response
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        try {
            $this->handler->delete($accountId, $this->getAuthenticatedUser()->getId());
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->deletedResponse();
    }
}
