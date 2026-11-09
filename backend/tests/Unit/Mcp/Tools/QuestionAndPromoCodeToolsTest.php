<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\PromoCodes\CreatePromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\GetPromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\UpdatePromoCodeAction;
use HiEvents\Http\Actions\Questions\CreateQuestionAction;
use HiEvents\Http\Actions\Questions\EditQuestionAction;
use HiEvents\Http\Actions\Questions\GetQuestionAction;
use HiEvents\Mcp\Tools\CreatePromoCodeTool;
use HiEvents\Mcp\Tools\CreateQuestionTool;
use HiEvents\Mcp\Tools\UpdatePromoCodeTool;
use HiEvents\Mcp\Tools\UpdateQuestionTool;
use Laravel\Mcp\Request;

class QuestionAndPromoCodeToolsTest extends McpToolTestCase
{
    public function test_create_question_defaults_to_an_optional_order_question(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateQuestionAction::class, ['event_id' => 12], [
                'belongs_to' => 'ORDER',
                'required' => false,
                'is_hidden' => false,
                'title' => 'Dietary requirements?',
                'type' => 'RADIO',
                'options' => ['No', 'Yes'],
            ])
            ->andReturn($this->ok($this->question()));

        $result = $this->structured((new CreateQuestionTool($this->api))->handle(new Request([
            'event_id' => 12, 'title' => 'Dietary requirements?', 'type' => 'RADIO', 'options' => ['No', 'Yes'],
        ])));

        $this->assertSame(['No', 'Yes'], $result['question']['options']);
    }

    public function test_update_question_resends_the_current_question_but_leaves_logic_to_the_api(): void
    {
        $this->api->shouldReceive('get')->with(GetQuestionAction::class, ['event_id' => 12, 'question_id' => 7])->andReturn($this->ok($this->question([
            'condition_question_id' => 3,
            'condition_values' => ['Yes'],
            'product_ids' => [5],
        ])));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(function (string $method, string $action, array $parameters, array $body) {
                return $method === 'PUT'
                    && $action === EditQuestionAction::class
                    && $body['title'] === 'Dietary requirements?'
                    && $body['options'] === ['No', 'Yes, please']
                    && $body['option_renames'] === [['from' => 'Yes', 'to' => 'Yes, please']]
                    && $body['product_ids'] === [5]
                    && $body['required'] === true
                    && ! array_key_exists('condition_question_id', $body)
                    && ! array_key_exists('disqualifying_options', $body);
            })
            ->andReturn($this->ok($this->question()));

        $this->structured((new UpdateQuestionTool($this->api))->handle(new Request([
            'event_id' => 12,
            'question_id' => 7,
            'options' => ['No', 'Yes, please'],
            'option_renames' => [['from' => 'Yes', 'to' => 'Yes, please']],
        ])));
    }

    public function test_update_question_passes_an_explicit_null_to_remove_a_condition(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->ok($this->question(['condition_question_id' => 3])));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $method, string $action, array $parameters, array $body) => array_key_exists('condition_question_id', $body) && $body['condition_question_id'] === null)
            ->andReturn($this->ok($this->question()));

        $this->structured((new UpdateQuestionTool($this->api))->handle(new Request(['event_id' => 12, 'question_id' => 7, 'condition_question_id' => null])));
    }

    public function test_update_question_keeps_the_parent_when_only_condition_values_change(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->ok($this->question(['condition_question_id' => 3, 'condition_values' => ['Yes']])));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $method, string $action, array $parameters, array $body) => $body['condition_question_id'] === 3
                && $body['condition_values'] === ['Vegan', 'Vegetarian']
                && ! array_key_exists('disqualifying_options', $body))
            ->andReturn($this->ok($this->question()));

        $this->structured((new UpdateQuestionTool($this->api))->handle(new Request(['event_id' => 12, 'question_id' => 7, 'condition_values' => ['Vegan', 'Vegetarian']])));
    }

    public function test_update_question_keeps_disqualifying_options_when_only_the_message_changes(): void
    {
        $this->api->shouldReceive('get')->andReturn($this->ok($this->question(['disqualifying_options' => ['Under 18'], 'disqualification_message' => 'Adults only.'])));
        $this->api->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $method, string $action, array $parameters, array $body) => $body['disqualifying_options'] === ['Under 18']
                && $body['disqualification_message'] === 'You must be 18 or over.'
                && ! array_key_exists('condition_question_id', $body))
            ->andReturn($this->ok($this->question()));

        $this->structured((new UpdateQuestionTool($this->api))->handle(new Request(['event_id' => 12, 'question_id' => 7, 'disqualification_message' => 'You must be 18 or over.'])));
    }

    public function test_create_promo_code_sends_safe_defaults(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreatePromoCodeAction::class, ['event_id' => 12], [
                'discount_applies_to' => 'EACH_PRODUCT',
                'applicable_product_ids' => [],
                'code' => 'EARLY20',
                'discount_type' => 'PERCENTAGE',
                'discount' => 20,
            ])
            ->andReturn($this->ok($this->promoCode()));

        $result = $this->structured((new CreatePromoCodeTool($this->api))->handle(new Request([
            'event_id' => 12, 'code' => 'EARLY20', 'discount_type' => 'PERCENTAGE', 'discount' => 20,
        ])));

        $this->assertSame('2027-05-31T20:00:00-04:00', $result['promo_code']['expiry_date']);
    }

    public function test_update_promo_code_keeps_the_current_values_in_local_time(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('get')->with(GetPromoCodeAction::class, ['event_id' => 12, 'promo_code_id' => 4])->andReturn($this->ok($this->promoCode()));
        $this->api->shouldReceive('send')
            ->once()
            ->with('PUT', UpdatePromoCodeAction::class, ['event_id' => 12, 'promo_code_id' => 4], [
                'code' => 'early20',
                'discount_type' => 'PERCENTAGE',
                'discount' => 20,
                'discount_applies_to' => 'ORDER',
                'applicable_product_ids' => [5],
                'expiry_date' => '2027-05-31 20:00',
                'max_allowed_usages' => 100,
            ])
            ->andReturn($this->ok($this->promoCode(['max_allowed_usages' => 100])));

        $this->structured((new UpdatePromoCodeTool($this->api))->handle(new Request(['event_id' => 12, 'promo_code_id' => 4, 'max_allowed_usages' => 100])));
    }

    private function question(array $overrides = []): array
    {
        return [
            'id' => 7,
            'title' => 'Dietary requirements?',
            'type' => 'RADIO',
            'belongs_to' => 'ORDER',
            'options' => ['No', 'Yes'],
            'required' => true,
            'is_hidden' => false,
            'applies_to_all_tickets' => false,
            ...$overrides,
        ];
    }

    private function promoCode(array $overrides = []): array
    {
        return [
            'id' => 4,
            'code' => 'early20',
            'discount_type' => 'PERCENTAGE',
            'discount' => 20,
            'discount_applies_to' => 'ORDER',
            'applicable_product_ids' => [5],
            'expiry_date' => '2027-06-01 00:00:00',
            'max_allowed_usages' => null,
            ...$overrides,
        ];
    }
}
