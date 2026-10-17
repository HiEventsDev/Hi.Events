<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Services\Application\Handlers\Account\Sso\AccountSsoConnectionHandler;
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
        $this->assertEnterpriseSsoEnabled($accountId);

        try {
            $this->handler->delete($accountId, $this->getAuthenticatedUser()->getId());
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->deletedResponse();
    }
}
