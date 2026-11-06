<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Questions\GetQuestionsAction;
use HiEvents\Mcp\Tools\Support\QuestionPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_questions')]
#[Title('List registration questions')]
#[Description('Lists the questions an event asks at checkout, in order, with their follow-up conditions and disqualifying answers.')]
#[IsReadOnly]
class ListQuestionsTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        $response = $this->api->get(GetQuestionsAction::class, ['event_id' => $eventId]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured([
            'questions' => collect($response->data())->map(QuestionPayload::present(...))->values()->all(),
        ]);
    }
}
