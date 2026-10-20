<?php

namespace HiEvents\Enterprise\Sso\Http\Actions;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\Sso\Exceptions\AccountSsoConfigurationException;
use HiEvents\Enterprise\Sso\Http\Request\UpsertAccountSsoConnectionRequest;
use HiEvents\Enterprise\Sso\Resources\AccountSsoConnectionResource;
use HiEvents\Enterprise\Sso\Services\Application\Handlers\AccountSsoConnectionHandler;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Http\ResponseCodes;
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
