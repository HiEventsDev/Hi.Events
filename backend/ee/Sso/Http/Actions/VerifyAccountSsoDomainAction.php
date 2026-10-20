<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Resources\AccountSsoDomainResource;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\AccountSsoDomainHandler;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class VerifyAccountSsoDomainAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly AccountSsoDomainHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(int $accountId, int $domainId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $this->assertEnterpriseSsoEnabled($accountId);

        try {
            $domain = $this->handler->verify($accountId, $domainId);
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(AccountSsoDomainResource::class, $domain);
    }
}
