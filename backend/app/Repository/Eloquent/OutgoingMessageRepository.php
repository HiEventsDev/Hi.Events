<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OutgoingMessageDomainObject;
use HiEvents\DomainObjects\Status\OutgoingMessageStatus;
use HiEvents\Models\OutgoingMessage;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;
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
            ->where('status', OutgoingMessageStatus::SENT->name)
            ->update(['status' => OutgoingMessageStatus::BOUNCED->name]);

        return $affected > 0;
    }
}
