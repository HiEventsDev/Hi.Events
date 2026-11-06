<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Questions\DeleteQuestionAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\DeleteQuestionTool;
use Laravel\Mcp\Request;

class DeleteQuestionToolTest extends McpToolTestCase
{
    public function test_it_deletes_the_question_on_its_event(): void
    {
        $this->api->shouldReceive('send')
            ->once()
            ->with('DELETE', DeleteQuestionAction::class, ['event_id' => 12, 'question_id' => 7])
            ->andReturn(new ApiRouteResponseDTO(ResponseCodes::HTTP_NO_CONTENT, []));

        $this->assertSame(['deleted_question_id' => 7], $this->structured((new DeleteQuestionTool($this->api))->handle(new Request([
            'event_id' => 12, 'question_id' => 7,
        ]))));
    }

    public function test_a_question_with_answers_is_kept_and_the_reason_returned(): void
    {
        $this->api->shouldReceive('send')->andReturn($this->failed(ResponseCodes::HTTP_CONFLICT, [
            'message' => 'You cannot delete this question as there as answers associated with it. You can hide the question instead.',
        ]));

        $this->assertStringContainsString('hide the question', $this->errorText((new DeleteQuestionTool($this->api))->handle(new Request([
            'event_id' => 12, 'question_id' => 7,
        ]))));
    }
}
