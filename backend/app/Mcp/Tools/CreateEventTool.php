<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\Http\Actions\Events\CreateEventAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_event')]
#[Title('Create event')]
#[Description('Creates a draft event for an organizer. A SINGLE event needs a start date and gets that date straight away. A RECURRING event starts with no dates. The event is not public until it is published.')]
#[IsDestructive]
class CreateEventTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_id' => $schema->integer()->description('The organizer the event belongs to.')->required(),
            'title' => $schema->string()->description('Event title, up to 150 characters.')->required(),
            'description' => $schema->string()->description('Event description as HTML (p, h3, ul, li, strong, em, a).'),
            'type' => $schema->string()->enum(EventType::valuesArray())->description('SINGLE (default) or RECURRING.'),
            'start_date' => $schema->string()->description('Required for SINGLE events. '.McpPresenter::LOCAL_DATE_DESCRIPTION),
            'end_date' => $schema->string()->description(McpPresenter::LOCAL_DATE_DESCRIPTION),
            'timezone' => $schema->string()->description('IANA timezone. Defaults to the organizer timezone.'),
            'currency' => $schema->string()->description('ISO 4217 currency code. Defaults to the organizer currency.'),
            'category' => $schema->string()->enum(EventCategory::valuesArray())->description('Event category.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('POST', CreateEventAction::class, [], $request->only([
            'organizer_id', 'title', 'description', 'type', 'start_date', 'end_date', 'timezone', 'currency', 'category',
        ]));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['event' => McpPresenter::eventSummary($response->data())]);
    }
}
