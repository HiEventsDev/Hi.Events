<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\Http\Actions\Lineup\GetProfilesAction;
use HiEvents\Mcp\Tools\ListPerformerProfilesTool;
use Laravel\Mcp\Request;

class ListPerformerProfilesToolTest extends McpToolTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->api->shouldReceive('get')->with(GetProfilesAction::class, ['organizer_id' => 3])->andReturn($this->ok([
            ['id' => 40, 'name' => 'The Ensemble', 'tagline' => 'Jazz', 'images' => [['id' => 1]]],
            ['id' => 41, 'name' => 'Dr. Ada Lovelace', 'tagline' => null, 'images' => []],
        ]));
    }

    public function test_it_lists_profiles_with_whether_they_have_a_photo(): void
    {
        $result = $this->structured((new ListPerformerProfilesTool($this->api))->handle(new Request(['organizer_id' => 3])));

        $this->assertSame([
            ['id' => 40, 'name' => 'The Ensemble', 'tagline' => 'Jazz', 'has_image' => true],
            ['id' => 41, 'name' => 'Dr. Ada Lovelace', 'tagline' => null, 'has_image' => false],
        ], $result['profiles']);
    }

    public function test_search_matches_names_case_insensitively(): void
    {
        $result = $this->structured((new ListPerformerProfilesTool($this->api))->handle(new Request(['organizer_id' => 3, 'search' => 'ADA'])));

        $this->assertSame([41], array_column($result['profiles'], 'id'));
    }
}
