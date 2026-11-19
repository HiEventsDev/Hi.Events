<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Bulk;

use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\Enterprise\Crm\Http\Request\BulkContactsRequest;
use Illuminate\Http\JsonResponse;

class BulkDeleteContactsAction extends BaseBulkContactsAction
{
    public function __invoke(BulkContactsRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->startBulkOperation($request, $organizerId, CrmBulkAction::DELETE);
    }
}
