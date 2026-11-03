<?php

namespace Tests\Feature\Http\Actions\Questions;

use HiEvents\Models\Order;
use HiEvents\Models\QuestionAnswer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class QuestionConditionsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $eventId;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        [$owner, $accountId] = $this->createAccountOwner();
        $this->eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));
        $this->headers = $this->headersFor($owner, $accountId);
    }

    public function test_a_follow_up_is_created_and_placed_after_its_parent(): void
    {
        $parentId = $this->createQuestion(['title' => 'Dietary needs', 'type' => 'RADIO', 'options' => ['None', 'Other']]);
        $laterId = $this->createQuestion(['title' => 'How did you hear about us?']);

        $followUpId = $this->postJson("/events/$this->eventId/questions", $this->payload([
            'title' => 'Please specify',
            'required' => true,
            'condition_question_id' => $parentId,
            'condition_values' => ['Other'],
        ]), $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.condition_question_id', $parentId)
            ->assertJsonPath('data.condition_values', ['Other'])
            ->json('data.id');

        $this->assertSame([$parentId, $followUpId, $laterId], $this->questionIdsInOrder());

        $this->getJson("/public/events/$this->eventId/questions")
            ->assertOk()
            ->assertJsonFragment(['id' => $followUpId, 'condition_question_id' => $parentId, 'condition_values' => ['Other']]);
    }

    public function test_an_invalid_condition_is_a_validation_error(): void
    {
        $textQuestionId = $this->createQuestion(['title' => 'Company']);

        $this->postJson("/events/$this->eventId/questions", $this->payload([
            'condition_question_id' => $textQuestionId,
            'condition_values' => ['Acme'],
        ]), $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition_question_id']);
    }

    public function test_a_condition_on_another_events_question_is_rejected(): void
    {
        [, $otherAccountId] = $this->createAccountOwner();
        $otherEventId = $this->createEvent($otherAccountId, $this->createOrganizer($otherAccountId));
        $foreignQuestionId = DB::table('questions')->insertGetId([
            'event_id' => $otherEventId,
            'title' => 'Member?',
            'type' => 'RADIO',
            'options' => json_encode(['Yes', 'No']),
            'belongs_to' => 'ORDER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/events/$this->eventId/questions", $this->payload([
            'condition_question_id' => $foreignQuestionId,
            'condition_values' => ['Yes'],
        ]), $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition_question_id']);
    }

    public function test_editing_without_condition_fields_keeps_the_condition_and_null_clears_it(): void
    {
        $parentId = $this->createQuestion(['type' => 'RADIO', 'options' => ['Yes', 'No']]);
        $followUpId = $this->createQuestion(['condition_question_id' => $parentId, 'condition_values' => ['Yes']]);

        $this->putJson("/events/$this->eventId/questions/$followUpId", $this->payload(['title' => 'Renamed']), $this->headers)
            ->assertOk()
            ->assertJsonPath('data.condition_question_id', $parentId);

        $this->putJson("/events/$this->eventId/questions/$followUpId", $this->payload(['condition_question_id' => null]), $this->headers)
            ->assertOk()
            ->assertJsonPath('data.condition_question_id', null)
            ->assertJsonPath('data.condition_values', []);
    }

    public function test_renaming_a_parent_option_updates_its_follow_ups(): void
    {
        $parentId = $this->createQuestion(['type' => 'RADIO', 'options' => ['None', 'Other']]);
        $followUpId = $this->createQuestion(['condition_question_id' => $parentId, 'condition_values' => ['Other']]);

        $this->putJson("/events/$this->eventId/questions/$parentId", $this->payload([
            'type' => 'RADIO',
            'options' => ['None', 'Something else'],
            'option_renames' => [['from' => 'Other', 'to' => 'Something else']],
        ]), $this->headers)->assertOk();

        $this->getJson("/events/$this->eventId/questions/$followUpId", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.condition_values', ['Something else']);
    }

    public function test_renaming_an_ineligible_option_keeps_it_ineligible_when_the_field_is_omitted(): void
    {
        $questionId = $this->createQuestion([
            'type' => 'RADIO',
            'options' => ['Yes', 'No'],
            'disqualifying_options' => ['No'],
            'disqualification_message' => 'Adults only.',
        ]);

        $this->putJson("/events/$this->eventId/questions/$questionId", $this->payload([
            'type' => 'RADIO',
            'options' => ['Yes', 'Not yet'],
            'option_renames' => [['from' => 'No', 'to' => 'Not yet']],
        ]), $this->headers)
            ->assertOk()
            ->assertJsonPath('data.disqualifying_options', ['Not yet'])
            ->assertJsonPath('data.disqualification_message', 'Adults only.');
    }

    public function test_removing_an_option_a_follow_up_uses_is_a_validation_error(): void
    {
        $parentId = $this->createQuestion(['type' => 'RADIO', 'options' => ['None', 'Other']]);
        $this->createQuestion(['condition_question_id' => $parentId, 'condition_values' => ['Other']]);

        $this->putJson("/events/$this->eventId/questions/$parentId", $this->payload([
            'type' => 'RADIO',
            'options' => ['None'],
        ]), $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options']);
    }

    public function test_ineligible_answers_are_saved_and_make_the_question_required(): void
    {
        $this->postJson("/events/$this->eventId/questions", $this->payload([
            'type' => 'RADIO',
            'options' => ['Yes', 'No'],
            'required' => false,
            'disqualifying_options' => ['No'],
            'disqualification_message' => 'This event is for healthcare professionals only.',
        ]), $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.required', true)
            ->assertJsonPath('data.disqualifying_options', ['No'])
            ->assertJsonPath('data.disqualification_message', 'This event is for healthcare professionals only.');
    }

    public function test_deleting_a_parent_deletes_its_follow_ups(): void
    {
        $parentId = $this->createQuestion(['type' => 'RADIO', 'options' => ['Yes', 'No']]);
        $followUpId = $this->createQuestion(['type' => 'RADIO', 'options' => ['A', 'B'], 'condition_question_id' => $parentId, 'condition_values' => ['Yes']]);
        $nestedId = $this->createQuestion(['condition_question_id' => $followUpId, 'condition_values' => ['A']]);
        $unrelatedId = $this->createQuestion(['title' => 'Unrelated']);

        $this->deleteJson("/events/$this->eventId/questions/$parentId", [], $this->headers)->assertNoContent();

        $this->assertSame(
            [$unrelatedId],
            DB::table('questions')->where('event_id', $this->eventId)->whereNull('deleted_at')->pluck('id')->all(),
        );
        $this->assertNotNull(DB::table('questions')->where('id', $nestedId)->value('deleted_at'));
    }

    public function test_deleting_a_parent_is_refused_when_a_follow_up_has_answers(): void
    {
        $parentId = $this->createQuestion(['type' => 'RADIO', 'options' => ['Yes', 'No']]);
        $followUpId = $this->createQuestion(['condition_question_id' => $parentId, 'condition_values' => ['Yes']]);
        $orderId = Order::forceCreate([
            'event_id' => $this->eventId,
            'short_id' => 'ord_followup',
            'public_id' => 'pub-followup',
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ada@example.com',
            'total_before_additions' => 0,
            'total_gross' => 0,
            'currency' => 'USD',
            'status' => 'COMPLETED',
        ])->id;
        QuestionAnswer::forceCreate([
            'question_id' => $followUpId,
            'order_id' => $orderId,
            'answer' => ['answer' => 'Peanuts'],
        ]);

        $this->deleteJson("/events/$this->eventId/questions/$parentId", [], $this->headers)
            ->assertStatus(400)
            ->assertJsonPath('message', __('You cannot delete this question because its follow-up questions have answers. You can hide the question instead.'));

        $this->assertSame(
            [$parentId, $followUpId],
            DB::table('questions')->where('event_id', $this->eventId)->whereNull('deleted_at')->orderBy('order')->pluck('id')->all(),
        );
    }

    private function createQuestion(array $overrides): int
    {
        return $this->postJson("/events/$this->eventId/questions", $this->payload($overrides), $this->headers)
            ->assertCreated()
            ->json('data.id');
    }

    private function questionIdsInOrder(): array
    {
        return DB::table('questions')
            ->where('event_id', $this->eventId)
            ->whereNull('deleted_at')
            ->orderBy('order')
            ->pluck('id')
            ->all();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Question',
            'type' => 'SINGLE_LINE_TEXT',
            'belongs_to' => 'ORDER',
            'product_ids' => [],
            'required' => false,
            'is_hidden' => false,
        ], $overrides);
    }
}
