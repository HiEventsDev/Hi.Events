<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Attendees\GetAttendeeAction;
use HiEvents\Http\Actions\Attendees\ResendAttendeeTicketAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('resend_ticket')]
#[Title('Resend ticket')]
#[Description('Emails an active attendee their ticket again, to the email address already on the attendee. Each attendee can be sent their ticket at most once every 10 minutes, and an email address gets at most 5 emails from these tools every 10 minutes.')]
#[IsDestructive]
class ResendTicketTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'attendee_id' => $schema->integer()->description('The attendee id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'attendee_id' => 'required|integer|min:1',
        ]);

        $eventId = (int) $validated['event_id'];
        $attendeeId = (int) $validated['attendee_id'];
        $recipientKey = "attendee:$eventId:$attendeeId";

        if ($cooling = $this->resendCooldownResponse($recipientKey)) {
            return $cooling;
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $attendee = $this->api->get(GetAttendeeAction::class, ['event_id' => $eventId, 'attendee_id' => $attendeeId]);

        if (! $attendee->isSuccessful()) {
            return $this->errorResponse($attendee);
        }

        $email = (string) ($attendee->data()['email'] ?? '');

        if ($overBudget = $this->emailBudgetResponse($email)) {
            return $overBudget;
        }

        $response = $this->api->send('POST', ResendAttendeeTicketAction::class, ['event_id' => $eventId, 'attendee_public_id' => $attendeeId]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $this->startResendCooldown($recipientKey);
        $this->spendEmailBudget($email);

        return Response::structured(['attendee_id' => $attendeeId, 'ticket_sent' => true]);
    }
}
