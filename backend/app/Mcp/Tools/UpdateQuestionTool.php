<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Questions\EditQuestionAction;
use HiEvents\Http\Actions\Questions\GetQuestionAction;
use HiEvents\Mcp\Tools\Support\QuestionPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_question')]
#[Title('Update registration question')]
#[Description('Changes a registration question. Fields you leave out stay as they are. To rename an option that follow-ups depend on, pass option_renames so their conditions move with it; removing such an option is refused.')]
#[IsDestructive]
class UpdateQuestionTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'question_id' => $schema->integer()->description('The question id.')->required(),
            ...QuestionPayload::schema($schema),
            'option_renames' => $schema->array()->items($schema->object([
                'from' => $schema->string()->required(),
                'to' => $schema->string()->required(),
            ]))->description('Options renamed in this change, old label to new label.'),
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

        $routeParameters = ['event_id' => $validated['event_id'], 'question_id' => $validated['question_id']];

        $existing = $this->api->get(GetQuestionAction::class, $routeParameters);

        if (! $existing->isSuccessful()) {
            return $this->errorResponse($existing);
        }

        $response = $this->api->send('PUT', EditQuestionAction::class, $routeParameters, [
            ...QuestionPayload::fromExisting($existing->data()),
            ...QuestionPayload::completeLinkedFields($existing->data(), $request->only([...QuestionPayload::FIELDS, 'option_renames'])),
        ]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['question' => QuestionPayload::present($response->data())]);
    }
}
