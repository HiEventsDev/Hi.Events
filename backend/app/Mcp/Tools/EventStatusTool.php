<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\Actions\Events\UpdateEventStatusAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

abstract class EventStatusTool extends WriteApiTool
{
    abstract protected function status(): EventStatus;

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('PUT', UpdateEventStatusAction::class, ['event_id' => $eventId], [
            'status' => $this->status()->name,
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['event' => McpPresenter::eventSummary($response->data())]);
    }
}
