<?php

namespace HiEvents\Http\Actions\Accounts;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Account\UpdateAccountRequest;
use HiEvents\Resources\Account\AccountResource;
use HiEvents\Services\Handlers\Account\DTO\UpdateAccountDTO;
use HiEvents\Services\Handlers\Account\UpdateAccountHanlder;
use Illuminate\Http\JsonResponse;

class UpdateAccountAction extends BaseAction
{
    private UpdateAccountHanlder $updateAccountHandler;

    public function __construct(UpdateAccountHanlder $updateAccountHandler)
    {
        $this->updateAccountHandler = $updateAccountHandler;
    }

    public function __invoke(UpdateAccountRequest $request): JsonResponse
    {
        $this->minimumAllowedRole(Role::ADMIN);

        $authUser = $this->getAuthenticatedUser();

        $payload = array_merge($request->validated(), [
            'account_id' => $this->getAuthenticatedAccountId(),
            'updated_by_user_id' => $authUser->getId(),
        ]);

        $account = $this->updateAccountHandler->handle(UpdateAccountDTO::fromArray($payload));

        return $this->resourceResponse(AccountResource::class, $account);
    }
}
