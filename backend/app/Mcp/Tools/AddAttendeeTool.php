<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Attendees\CreateAttendeeAction;
use HiEvents\Locale;
use HiEvents\Mcp\Tools\Support\SalesPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('add_attendee')]
#[Title('Add complimentary attendee')]
#[Description('Adds a complimentary attendee to an event, e.g. a guest-list name or a speaker pass, as a free manual order. Uses up one of the ticket\'s available places and never oversells. By default the attendee is emailed their ticket and order summary; an email address gets at most 5 emails from these tools every 10 minutes. Tiered tickets use their first tier unless a tier is given. Seated tickets can only be added from the dashboard.')]
#[IsDestructive]
class AddAttendeeTool extends WriteApiTool
{
    public const DEFAULT_LOCALE = 'en';

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'ticket_id' => $schema->integer()->description('The ticket to issue.')->required(),
            'tier_id' => $schema->integer()->description('The price tier, for tiered tickets.'),
            'date_id' => $schema->integer()->description('The date, for recurring events.'),
            'first_name' => $schema->string()->required(),
            'last_name' => $schema->string()->required(),
            'email' => $schema->string()->description('Where the ticket is sent.')->required(),
            'send_ticket' => $schema->boolean()->description('Email the attendee their ticket. Defaults to true.'),
            'locale' => $schema->string()->enum(Locale::getSupportedLocales())->description('Language of the ticket email. Defaults to en.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'ticket_id' => 'required|integer|min:1',
            'tier_id' => 'nullable|integer|min:1',
            'date_id' => 'nullable|integer|min:1',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string',
            'send_ticket' => 'nullable|boolean',
            'locale' => ['nullable', Rule::in(Locale::getSupportedLocales())],
        ]);

        $sendTicket = (bool) ($validated['send_ticket'] ?? true);

        if ($sendTicket && $overBudget = $this->emailBudgetResponse($validated['email'])) {
            return $overBudget;
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $eventId = (int) $validated['event_id'];
        $event = $this->getEvent($eventId);

        if (! $event->isSuccessful()) {
            return $this->errorResponse($event);
        }

        $response = $this->api->send('POST', CreateAttendeeAction::class, ['event_id' => $eventId], array_filter([
            'product_id' => (int) $validated['ticket_id'],
            'product_price_id' => $validated['tier_id'] ?? null,
            'event_occurrence_id' => $validated['date_id'] ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'amount_paid' => 0,
            'send_confirmation_email' => $sendTicket,
            'locale' => $validated['locale'] ?? self::DEFAULT_LOCALE,
            'override_capacity' => false,
        ], static fn (mixed $value): bool => $value !== null));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        if ($sendTicket) {
            $this->spendEmailBudget($validated['email']);
        }

        return Response::structured([
            'attendee' => SalesPresenter::attendee($response->data(), $event->data()['timezone']),
        ]);
    }
}
