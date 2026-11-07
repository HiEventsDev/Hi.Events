<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\Http\Actions\Questions\CreateQuestionAction;
use HiEvents\Mcp\Tools\Support\QuestionPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_question')]
#[Title('Create registration question')]
#[Description('Adds a question buyers answer at checkout, asked once per order or for each ticket. It can be a follow-up shown only for certain answers to another choice question, and can have disqualifying answers that stop registration. Follow-ups can nest up to 5 levels.')]
#[IsDestructive]
class CreateQuestionTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        $fields = QuestionPayload::schema($schema);
        $fields['title']->required();
        $fields['type']->required();

        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            ...$fields,
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $eventId = (int) $request->validate(['event_id' => 'required|integer|min:1'])['event_id'];

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('POST', CreateQuestionAction::class, ['event_id' => $eventId], [
            'belongs_to' => QuestionBelongsTo::ORDER->name,
            'required' => false,
            'is_hidden' => false,
            ...$request->only(QuestionPayload::FIELDS),
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['question' => QuestionPayload::present($response->data())]);
    }
}
