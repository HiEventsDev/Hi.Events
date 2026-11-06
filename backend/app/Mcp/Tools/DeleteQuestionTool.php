<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Questions\DeleteQuestionAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_question')]
#[Title('Delete registration question')]
#[Description('Deletes a registration question and its follow-ups. Refused once the question or any of its follow-ups has answers; hide it instead.')]
#[IsDestructive]
class DeleteQuestionTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'question_id' => $schema->integer()->description('The question id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'question_id' => 'required|integer|min:1',
        ]);

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('DELETE', DeleteQuestionAction::class, [
            'event_id' => $validated['event_id'],
            'question_id' => $validated['question_id'],
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['deleted_question_id' => (int) $validated['question_id']]);
    }
}
