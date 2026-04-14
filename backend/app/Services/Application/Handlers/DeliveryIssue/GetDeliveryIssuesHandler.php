<?php

namespace HiEvents\Services\Application\Handlers\DeliveryIssue;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GetDeliveryIssuesHandler
{
    public function handle(int $eventId, int $perPage = 20, int $page = 1, bool $showResolved = false): LengthAwarePaginator
    {
        $excludedStatuses = ['SENT', 'DELIVERED'];
        $resolvedFilter = !$showResolved ? 'AND resolved_at IS NULL' : '';

        $sql = "
            SELECT id, 'transaction' as source_type, email_type, status, subject, recipient, updated_at, resolved_at, retry_for_id
            FROM outgoing_transaction_messages
            WHERE event_id = ? AND status NOT IN (?, ?) AND deleted_at IS NULL {$resolvedFilter}

            UNION ALL

            SELECT id, 'announcement' as source_type, 'announcement' as email_type, status, subject, recipient, updated_at, resolved_at, retry_for_id
            FROM outgoing_messages
            WHERE event_id = ? AND status NOT IN (?, ?) AND deleted_at IS NULL {$resolvedFilter}
        ";

        $bindings = [$eventId, ...$excludedStatuses, $eventId, ...$excludedStatuses];

        $countQuery = DB::select("SELECT COUNT(*) as total FROM ({$sql}) as delivery_issues", $bindings);
        $total = $countQuery[0]->total;

        $offset = ($page - 1) * $perPage;

        $results = DB::select(
            "{$sql} ORDER BY updated_at DESC LIMIT ? OFFSET ?",
            [...$bindings, $perPage, $offset],
        );

        return new LengthAwarePaginator(
            items: collect($results),
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }
}
