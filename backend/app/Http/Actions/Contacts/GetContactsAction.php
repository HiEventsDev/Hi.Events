<?php

namespace HiEvents\Http\Actions\Contacts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;
use HiEvents\Resources\Contact\ContactResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetContactsAction extends BaseAction
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository,
    ) {}

    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $contacts = $this->contactRepository->findByAccountId(
            $this->getAuthenticatedAccountId(),
            QueryParamsDTO::fromArray($request->query->all()),
        );

        return $this->filterableResourceResponse(
            resource: ContactResource::class,
            data: $contacts,
            domainObject: ContactDomainObject::class,
        );
    }
}
