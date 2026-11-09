<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\EventOccurrences\GetOccurrenceGenerationStatusAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Routing\ApiRouteResponseDTO;
use HiEvents\Mcp\Tools\GetDateGenerationStatusTool;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

class GetDateGenerationStatusToolTest extends McpToolTestCase
{
    public function test_it_reports_the_job_status(): void
    {
        $this->api->shouldReceive('get')
            ->once()
            ->with(GetOccurrenceGenerationStatusAction::class, ['event_id' => 12], ['job_uuid' => 'job-1'])
            ->andReturn(new ApiRouteResponseDTO(ResponseCodes::HTTP_OK, ['status' => 'FINISHED', 'job_uuid' => 'job-1', 'message' => 'Job completed successfully']));

        $this->assertSame(
            ['status' => 'FINISHED', 'job_uuid' => 'job-1', 'message' => 'Job completed successfully'],
            $this->structured((new GetDateGenerationStatusTool($this->api))->handle(new Request(['event_id' => 12, 'job_uuid' => 'job-1']))),
        );
    }

    public function test_a_job_uuid_is_required(): void
    {
        $this->api->shouldNotReceive('get');

        $this->expectException(ValidationException::class);

        (new GetDateGenerationStatusTool($this->api))->handle(new Request(['event_id' => 12]));
    }
}
