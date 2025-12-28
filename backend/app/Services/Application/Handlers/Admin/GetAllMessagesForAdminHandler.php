<?php

namespace HiEvents\Services\Application\Handlers\Admin;

use HiEvents\Models\Message;
use HiEvents\Services\Application\Handlers\Admin\DTO\GetAllMessagesForAdminDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetAllMessagesForAdminHandler
{
    private const ALLOWED_SORT_COLUMNS = ['created_at', 'sent_at', 'subject', 'status', 'type'];

    public function handle(GetAllMessagesForAdminDTO $dto): LengthAwarePaginator
    {
        $query = Message::query()
            ->select([
                'messages.*',
                'events.title as event_title',
                'accounts.name as account_name',
                'users.first_name as sent_by_first_name',
                'users.last_name as sent_by_last_name',
            ])
            ->selectRaw('(SELECT COUNT(*) FROM outgoing_messages WHERE outgoing_messages.message_id = messages.id AND outgoing_messages.deleted_at IS NULL) as recipients_count')
            ->join('events', 'events.id', '=', 'messages.event_id')
            ->join('accounts', 'accounts.id', '=', 'events.account_id')
            ->join('users', 'users.id', '=', 'messages.sent_by_user_id')
            ->whereNull('messages.deleted_at');

        if ($dto->search) {
            $searchTerm = '%' . $dto->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('messages.subject', 'ilike', $searchTerm)
                    ->orWhere('events.title', 'ilike', $searchTerm)
                    ->orWhere('accounts.name', 'ilike', $searchTerm);
            });
        }

        if ($dto->status) {
            $query->where('messages.status', $dto->status);
        }

        if ($dto->type) {
            $query->where('messages.type', $dto->type);
        }

        $sortColumn = in_array($dto->sortBy, self::ALLOWED_SORT_COLUMNS, true) ? $dto->sortBy : 'created_at';
        $sortDirection = in_array(strtolower($dto->sortDirection ?? 'desc'), ['asc', 'desc']) ? $dto->sortDirection : 'desc';

        $query->orderBy('messages.' . $sortColumn, $sortDirection);

        return $query->paginate($dto->perPage);
    }
}
