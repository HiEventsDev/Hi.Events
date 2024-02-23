<?php

namespace TicketKitten\Http\Actions\Accounts;

use Illuminate\Http\JsonResponse;
use TicketKitten\DomainObjects\Enums\Role;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\UpdateAccountDTO;
use TicketKitten\Http\Request\Account\UpdateAccountRequest;
use TicketKitten\Resources\Account\AccountResource;
use TicketKitten\Service\Handler\Account\UpdateAccountHanlder;

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
            'account_id' => $authUser->getAccountId(),
            'updated_by_user_id' => $authUser->getId(),
        ]);

        $account = $this->updateAccountHandler->handle(UpdateAccountDTO::fromArray($payload));

        return $this->resourceResponse(AccountResource::class, $account);
    }
}
