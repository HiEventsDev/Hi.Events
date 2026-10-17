<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\Account\Sso\AddAccountSsoDomainRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Account\Sso\AccountSsoDomainResource;
use HiEvents\Services\Application\Handlers\Account\Sso\AccountSsoDomainHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class AddAccountSsoDomainAction extends BaseAccountSsoAction
{
    public function __construct(
        private readonly AccountSsoDomainHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(AddAccountSsoDomainRequest $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);
        $this->assertEnterpriseSsoEnabled($accountId);

        try {
            $domain = $this->handler->add($accountId, $request->validated('domain'));
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(AccountSsoDomainResource::class, $domain, ResponseCodes::HTTP_CREATED);
    }
}
