<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Services\Application\Handlers\Account\Sso\AccountSsoDomainHandler;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class DeleteAccountSsoDomainAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly AccountSsoDomainHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $accountId, int $domainId): Response
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $this->assertEnterpriseSsoEnabled($accountId);

        try {
            $this->handler->delete($accountId, $domainId);
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->deletedResponse();
    }
}
