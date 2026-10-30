<?php

namespace Tests\Feature\Http\Actions\Questions;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    public function test_new_questions_are_added_after_existing_questions(): void
    {
        $firstId = $this->postJson("/events/$this->eventId/questions", $this->payload(), $this->headers)
            ->assertCreated()
            ->json('data.id');
        $secondId = $this->postJson("/events/$this->eventId/questions", $this->payload(['title' => 'Job title']), $this->headers)
            ->assertCreated()
            ->json('data.id');

        $this->assertGreaterThan(
            DB::table('questions')->where('id', $firstId)->value('order'),
            DB::table('questions')->where('id', $secondId)->value('order'),
        );
    }

    public function test_a_question_for_all_tickets_applies_to_every_ticket_but_not_other_products(): void
    {
        $categoryId = $this->createCategory();
        $ticketId = $this->insertProduct($categoryId, 'TICKET');
        $this->insertProduct($categoryId, 'GENERAL');

        $this->postJson("/events/$this->eventId/questions", $this->payload([
            'belongs_to' => 'PRODUCT',
            'product_ids' => [],
            'applies_to_all_tickets' => true,
        ]), $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.applies_to_all_tickets', true)
            ->assertJsonPath('data.product_ids', [$ticketId]);
    }

    public function test_tickets_created_later_are_added_to_all_ticket_questions(): void
    {
        $categoryId = $this->createCategory();
        $questionId = $this->postJson("/events/$this->eventId/questions", $this->payload([
            'belongs_to' => 'PRODUCT',
            'applies_to_all_tickets' => true,
        ]), $this->headers)
            ->assertCreated()
            ->json('data.id');

        $ticketId = $this->postJson("/events/$this->eventId/products", $this->productPayload($categoryId, 'TICKET'), $this->headers)
            ->assertCreated()
            ->json('data.id');
        $this->postJson("/events/$this->eventId/products", $this->productPayload($categoryId, 'GENERAL'), $this->headers)
            ->assertCreated();

        $this->assertSame([$ticketId], $this->attachedProductIds($questionId));
    }

    public function test_a_ticket_changed_to_another_product_type_is_removed_from_all_ticket_questions(): void
    {
        $categoryId = $this->createCategory();
        $questionId = $this->postJson("/events/$this->eventId/questions", $this->payload([
            'belongs_to' => 'PRODUCT',
            'applies_to_all_tickets' => true,
        ]), $this->headers)
            ->assertCreated()
            ->json('data.id');
        $ticketId = $this->postJson("/events/$this->eventId/products", $this->productPayload($categoryId, 'TICKET'), $this->headers)
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/events/$this->eventId/products/$ticketId", $this->productPayload($categoryId, 'GENERAL'), $this->headers)
            ->assertOk();

        $this->assertSame([], $this->attachedProductIds($questionId));
    }

    public function test_tickets_are_not_added_to_questions_for_specific_tickets(): void
    {
        $categoryId = $this->createCategory();
        $existingTicketId = $this->insertProduct($categoryId, 'TICKET');
        $questionId = $this->postJson("/events/$this->eventId/questions", $this->payload([
            'belongs_to' => 'PRODUCT',
            'product_ids' => [$existingTicketId],
        ]), $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.applies_to_all_tickets', false)
            ->json('data.id');

        $this->postJson("/events/$this->eventId/products", $this->productPayload($categoryId, 'TICKET'), $this->headers)
            ->assertCreated();

        $this->assertSame([$existingTicketId], $this->attachedProductIds($questionId));
    }

    public function test_editing_without_the_all_tickets_flag_keeps_the_given_products(): void
    {
        $categoryId = $this->createCategory();
        $firstTicketId = $this->insertProduct($categoryId, 'TICKET');
        $this->insertProduct($categoryId, 'TICKET');
        $questionId = $this->postJson("/events/$this->eventId/questions", $this->payload([
            'belongs_to' => 'PRODUCT',
            'applies_to_all_tickets' => true,
        ]), $this->headers)
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/events/$this->eventId/questions/$questionId", $this->payload([
            'belongs_to' => 'PRODUCT',
            'product_ids' => [$firstTicketId],
        ]), $this->headers)
            ->assertOk()
            ->assertJsonPath('data.applies_to_all_tickets', false)
            ->assertJsonPath('data.product_ids', [$firstTicketId]);
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

    private function createCategory(): int
    {
        return DB::table('product_categories')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Tickets',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertProduct(int $categoryId, string $productType): int
    {
        return DB::table('products')->insertGetId([
            'title' => $productType.' product',
            'event_id' => $this->eventId,
            'product_category_id' => $categoryId,
            'type' => 'FREE',
            'product_type' => $productType,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function productPayload(int $categoryId, string $productType): array
    {
        return [
            'title' => $productType.' product',
            'type' => 'FREE',
            'product_type' => $productType,
            'product_category_id' => $categoryId,
            'prices' => [['price' => 0]],
        ];
    }

    private function attachedProductIds(int $questionId): array
    {
        return DB::table('product_questions')
            ->where('question_id', $questionId)
            ->whereNull('deleted_at')
            ->orderBy('product_id')
            ->pluck('product_id')
            ->all();
    }
}
