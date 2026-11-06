<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Questions\GetQuestionsAction;
use HiEvents\Mcp\Tools\ListQuestionsTool;
use Laravel\Mcp\Request;

class ListQuestionsToolTest extends McpToolTestCase
{
    public function test_it_lists_questions_with_their_logic_and_without_internal_fields(): void
    {
        $this->api->shouldReceive('get')->once()->with(GetQuestionsAction::class, ['event_id' => 12])->andReturn($this->ok([
            [
                'id' => 7, 'title' => 'Age group', 'type' => 'DROPDOWN', 'belongs_to' => 'PRODUCT', 'options' => [2 => '18+', 0 => 'Under 18'],
                'required' => true, 'is_hidden' => false, 'event_id' => 12, 'applies_to_all_tickets' => true, 'product_ids' => [5],
                'condition_question_id' => null, 'condition_values' => [], 'disqualifying_options' => ['Under 18'],
                'disqualification_message' => 'This event is 18+.',
            ],
        ]));

        $question = $this->structured((new ListQuestionsTool($this->api))->handle(new Request(['event_id' => 12])))['questions'][0];

        $this->assertSame(['18+', 'Under 18'], $question['options']);
        $this->assertSame(['Under 18'], $question['disqualifying_options']);
        $this->assertSame('This event is 18+.', $question['disqualification_message']);
        $this->assertArrayNotHasKey('event_id', $question);
    }
}
