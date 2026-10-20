<?php

namespace Tests\Feature\Http\Actions\Questions;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class UpsertQuestionActionTest extends TestCase
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

    public function test_an_order_question_can_be_created_and_edited(): void
    {
        $questionId = $this->postJson("/events/$this->eventId/questions", $this->payload(), $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.belongs_to', 'ORDER')
            ->assertJsonPath('data.type', 'SINGLE_LINE_TEXT')
            ->json('data.id');

        $this->putJson("/events/$this->eventId/questions/$questionId", $this->payload(['title' => 'Dietary needs']), $this->headers)
            ->assertOk()
            ->assertJsonPath('data.title', 'Dietary needs')
            ->assertJsonPath('data.belongs_to', 'ORDER');
    }

    public function test_an_order_question_can_be_created_and_edited_without_product_ids(): void
    {
        $payload = $this->payload();
        unset($payload['product_ids']);

        $questionId = $this->postJson("/events/$this->eventId/questions", $payload, $this->headers)
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/events/$this->eventId/questions/$questionId", [...$payload, 'title' => 'Dietary needs'], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.title', 'Dietary needs');
    }

    public function test_a_product_question_without_products_is_a_validation_error(): void
    {
        $this->postJson("/events/$this->eventId/questions", $this->payload(['belongs_to' => 'PRODUCT']), $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_ids'])
            ->assertJsonMissingValidationErrors(['belongs_to']);
    }

    public function test_an_unknown_belongs_to_is_a_validation_error_on_create_and_edit(): void
    {
        $this->postJson("/events/$this->eventId/questions", $this->payload(['belongs_to' => 'ATTENDEE']), $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['belongs_to']);

        $questionId = $this->postJson("/events/$this->eventId/questions", $this->payload(), $this->headers)
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/events/$this->eventId/questions/$questionId", $this->payload(['belongs_to' => 'order']), $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['belongs_to']);
    }

    public function test_a_missing_belongs_to_is_a_validation_error(): void
    {
        $payload = $this->payload();
        unset($payload['belongs_to']);

        $this->postJson("/events/$this->eventId/questions", $payload, $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['belongs_to']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Company name',
            'type' => 'SINGLE_LINE_TEXT',
            'belongs_to' => 'ORDER',
            'product_ids' => [],
            'required' => true,
            'is_hidden' => false,
        ], $overrides);
    }
}
