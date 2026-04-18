<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Contacts;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Services\Domain\Contact\ContactBackfillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetUnlinkedAttendeesBackfillAction extends BaseAction
{
    public function __construct(
        private readonly ContactBackfillService $service,
    ) {}

    public function __invoke(Request $request, int $accountId): JsonResponse
    {
        $this->isActionAuthorized($accountId, AccountDomainObject::class);

        $paginator = $this->service->getUnlinkedAttendees(
            $this->getAuthenticatedAccountId(),
            QueryParamsDTO::fromArray($request->query->all()),
            $request->boolean('include_processed'),
        );

        $array = $paginator->toArray();

        return $this->jsonResponse([
            'data' => $array['data'],
            'meta' => [
                'current_page' => $array['current_page'],
                'per_page' => $array['per_page'],
                'total' => $array['total'],
                'last_page' => $array['last_page'],
                'from' => $array['from'],
                'to' => $array['to'],
            ],
        ]);
    }
}
