<?php

namespace HiEvents\Http\Actions\TicketResale;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Actions\SelfService\RespondsWhenVerificationIsRequired;
use HiEvents\Services\Application\Handlers\TicketResale\DTO\TicketResaleLinkDTO;
use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;
use Illuminate\Http\Request;

abstract class BaseTicketResalePublicAction extends BaseAction
{
    use RespondsWhenVerificationIsRequired;

    protected function link(Request $request, int $eventId, string $orderShortId, string $attendeeShortId): TicketResaleLinkDTO
    {
        return new TicketResaleLinkDTO(
            event_id: $eventId,
            order_short_id: $orderShortId,
            attendee_short_id: $attendeeShortId,
            session_token: $request->header(SelfServiceVerificationService::SESSION_HEADER),
            ip_address: $this->getClientIp($request),
            user_agent: $request->userAgent(),
        );
    }
}
