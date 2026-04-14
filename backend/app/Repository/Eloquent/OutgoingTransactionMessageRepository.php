<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OutgoingTransactionMessageDomainObject;
use HiEvents\DomainObjects\Status\OutgoingTransactionMessageStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\OutgoingTransactionMessage;
use HiEvents\Repository\Interfaces\OutgoingTransactionMessageRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<OutgoingTransactionMessageDomainObject>
 */
class OutgoingTransactionMessageRepository extends BaseRepository implements OutgoingTransactionMessageRepositoryInterface
{
    protected function getModel(): string
    {
        return OutgoingTransactionMessage::class;
    }

    public function getDomainObject(): string
    {
        return OutgoingTransactionMessageDomainObject::class;
    }

    public function findBySesMessageId(string $sesMessageId): ?OutgoingTransactionMessageDomainObject
    {
        return $this->findFirstWhere([
            'ses_message_id' => $sesMessageId,
            ['status', 'in', [OutgoingTransactionMessageStatus::SENT->value, OutgoingTransactionMessageStatus::DELIVERED->value]],
        ]);
    }

    public function markAsBounced(int $id): void
    {
        $this->updateWhere(
            attributes: ['status' => OutgoingTransactionMessageStatus::BOUNCED->value],
            where: ['id' => $id],
        );
    }

    public function markAsDelivered(int $id): void
    {
        $this->updateWhere(
            attributes: ['status' => OutgoingTransactionMessageStatus::DELIVERED->value],
            where: ['id' => $id],
        );
    }

    public function getForEvent(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $query = OutgoingTransactionMessage::query()
            ->where('event_id', $eventId);

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
                    $query->whereIn('status', $values);
                }
                if ($filter->field === 'email_type' && !empty($filter->value)) {
                    $values = is_array($filter->value) ? $filter->value : explode(',', $filter->value);
                    $query->whereIn('email_type', $values);
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

        $query->orderByDesc('created_at');

        $results = $query->paginate(perPage: $params->per_page ?? 20, page: $params->page);

        return $this->handleResults($results);
    }

    public function getFailuresForEvent(int $eventId, int $perPage = 20, bool $showResolved = false): LengthAwarePaginator
    {
        $where = [
            'event_id' => $eventId,
            [
                'status',
                'in',
                [
                    OutgoingTransactionMessageStatus::BOUNCED->value,
                    OutgoingTransactionMessageStatus::FAILED->value,
                    OutgoingTransactionMessageStatus::SUPPRESSED->value,
                ],
            ],
        ];

        if (!$showResolved) {
            $where[] = ['resolved_at', '=', null];
        }

        return $this->paginateWhere(
            where: $where,
            limit: $perPage,
        );
    }

    public function findAccountIdByRecipientEmail(string $email): ?int
    {
        $result = DB::table('outgoing_transaction_messages')
            ->join('events', 'outgoing_transaction_messages.event_id', '=', 'events.id')
            ->where('outgoing_transaction_messages.recipient', strtolower($email))
            ->orderByDesc('outgoing_transaction_messages.created_at')
            ->select('events.account_id')
            ->first();

        return $result?->account_id;
    }
}
