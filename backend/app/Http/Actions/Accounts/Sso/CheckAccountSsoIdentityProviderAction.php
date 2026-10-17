<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\Account\Sso\UpsertAccountSsoConnectionRequest;
use HiEvents\Services\Application\Handlers\Account\Sso\AccountSsoConnectionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class CheckAccountSsoIdentityProviderAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly AccountSsoConnectionHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(UpsertAccountSsoConnectionRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $this->assertEnterpriseSsoEnabled($accountId);

        try {
            $this->handler->check($request->toConnectionData());
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->jsonResponse(['valid' => true], wrapInData: true);
    }
}
