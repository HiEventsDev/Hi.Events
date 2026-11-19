<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Bulk;

use HiEvents\DomainObjects\Enums\CrmBulkAction;
use HiEvents\Enterprise\Crm\Http\Request\BulkTagContactsRequest;
use Illuminate\Http\JsonResponse;

class BulkTagContactsAction extends BaseBulkContactsAction
{
    public function __invoke(BulkTagContactsRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        return $this->startBulkOperation(
            $request,
            $organizerId,
            CrmBulkAction::from($request->validated('action')),
            array_map('intval', $request->validated('tag_ids')),
        );
    }
}
