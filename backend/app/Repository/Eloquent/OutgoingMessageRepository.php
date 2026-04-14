<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OutgoingMessageDomainObject;
use HiEvents\DomainObjects\Status\OutgoingMessageStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\OutgoingMessage;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<OutgoingMessageDomainObject>
 */
class OutgoingMessageRepository extends BaseRepository implements OutgoingMessageRepositoryInterface
{
    protected function getModel(): string
    {
        return OutgoingMessage::class;
    }

    public function getDomainObject(): string
    {
        return OutgoingMessageDomainObject::class;
    }

    public function findAccountIdByRecipientEmail(string $email): ?int
    {
        $result = DB::table('outgoing_messages')
            ->join('events', 'outgoing_messages.event_id', '=', 'events.id')
            ->where('outgoing_messages.recipient', strtolower($email))
            ->orderByDesc('outgoing_messages.created_at')
            ->select('events.account_id')
            ->first();

        return $result?->account_id;
    }

    public function markAsBounced(string $sesMessageId): bool
    {
        $affected = DB::table('outgoing_messages')
            ->where('ses_message_id', $sesMessageId)
            ->whereIn('status', [OutgoingMessageStatus::SENT->name, OutgoingMessageStatus::DELIVERED->name])
            ->update(['status' => OutgoingMessageStatus::BOUNCED->name]);

        return $affected > 0;
    }

    public function getForEvent(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $table = 'outgoing_messages';
        $query = OutgoingMessage::query()
            ->where('event_id', $eventId)
            ->selectRaw("*,
                (SELECT COUNT(*) FROM {$table} r WHERE r.retry_for_id = {$table}.id) as retry_count,
                (SELECT r2.recipient FROM {$table} r2 WHERE r2.retry_for_id = {$table}.id ORDER BY r2.created_at DESC LIMIT 1) as latest_retry_recipient,
                (SELECT r3.status FROM {$table} r3 WHERE r3.retry_for_id = {$table}.id ORDER BY r3.created_at DESC LIMIT 1) as latest_retry_status,
                (SELECT o.recipient FROM {$table} o WHERE o.id = {$table}.retry_for_id) as original_recipient,
                (SELECT o2.status FROM {$table} o2 WHERE o2.id = {$table}.retry_for_id) as original_status");

        if ($params->query) {
            $search = '%' . $params->query . '%';
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'ilike', $search)
                    ->orWhere('subject', 'ilike', $search);
            });
        }

        if ($params->filter_fields) {
            foreach ($params->filter_fields as $filter) {
                if ($filter->field === 'status' && !empty($filter->value)) {
                    $values = is_array($filter->value) ? $filter->value : explode(',', $filter->value);
                    if (in_array('RESOLVED', $values)) {
                        $otherValues = array_filter($values, fn($v) => $v !== 'RESOLVED');
                        $query->where(function ($q) use ($otherValues) {
                            $q->whereNotNull('resolved_at');
                            if (!empty($otherValues)) {
                                $q->orWhereIn('status', $otherValues);
                            }
                        });
                    } else {
                        $query->whereIn('status', $values);
                    }
                }
                if ($filter->field === 'date_range' && !empty($filter->value)) {
                    $days = match ($filter->value) {
                        '1d' => 1, '7d' => 7, '30d' => 30, '90d' => 90, '365d' => 365, default => null,
                    };
                    if ($days) {
                        $query->where('created_at', '>=', now()->subDays($days));
                    }
                }
            }
        }

        $allowedSortFields = ['created_at', 'updated_at', 'status', 'recipient', 'subject'];
        $sortBy = in_array($params->sort_by, $allowedSortFields) ? $params->sort_by : 'created_at';
        $sortDir = strtolower($params->sort_direction ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $results = $query->paginate(perPage: $params->per_page ?? 20, page: $params->page);

        return $this->handleResults($results);
    }

    public function markAsDelivered(string $sesMessageId): bool
    {
        $affected = DB::table('outgoing_messages')
            ->where('ses_message_id', $sesMessageId)
            ->where('status', OutgoingMessageStatus::SENT->name)
            ->update(['status' => OutgoingMessageStatus::DELIVERED->name]);

        return $affected > 0;
    }
}
