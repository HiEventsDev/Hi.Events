<?php

namespace HiEvents\Http\Actions\Accounts\Sso;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Exceptions\AccountSsoConfigurationException;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\Request\Account\Sso\UpsertAccountSsoConnectionRequest;
use HiEvents\Http\ResponseCodes;
use HiEvents\Resources\Account\Sso\AccountSsoConnectionResource;
use HiEvents\Services\Application\Handlers\Account\Sso\AccountSsoConnectionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[RequiresPermission(Permission::MANAGE_ACCOUNT)]
class CreateAccountSsoConnectionAction extends BaseAccountSsoAction
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
            $connection = $this->handler->create($accountId, $this->getAuthenticatedUser()->getId(), $request->toConnectionData());
        } catch (AccountSsoConfigurationException $exception) {
            throw $this->configurationError($exception);
        }

        return $this->resourceResponse(AccountSsoConnectionResource::class, $connection, ResponseCodes::HTTP_CREATED);
    }
}
