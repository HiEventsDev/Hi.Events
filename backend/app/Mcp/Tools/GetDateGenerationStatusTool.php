<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\EventOccurrences\GetOccurrenceGenerationStatusAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_date_generation_status')]
#[Title('Get date generation status')]
#[Description('Checks whether generating an event\'s dates from a schedule has finished. Status is IN_PROGRESS, FINISHED, FAILED or NOT_FOUND.')]
#[IsReadOnly]
class GetDateGenerationStatusTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'job_uuid' => $schema->string()->description('The job_uuid returned when generation started.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'job_uuid' => 'required|string|max:64',
        ]);

        $response = $this->api->get(GetOccurrenceGenerationStatusAction::class, ['event_id' => $validated['event_id']], [
            'job_uuid' => $validated['job_uuid'],
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'status' => $response->body['status'],
            'job_uuid' => $response->body['job_uuid'],
            'message' => $response->body['message'],
        ]);
    }
}
