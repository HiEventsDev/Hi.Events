<?php

namespace Tests\Unit\Mcp\Tools;

use HiEvents\DomainObjects\Enums\ImageType;
use HiEvents\Exceptions\RemoteImageException;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\EventSettings\GetEventSettingsAction;
use HiEvents\Http\Actions\EventSettings\PartialEditEventSettingsAction;
use HiEvents\Http\Actions\Lineup\CreateLineupEntryAction;
use HiEvents\Http\Actions\Lineup\CreateProfileAction;
use HiEvents\Http\Actions\Lineup\DeleteProfileAction;
use HiEvents\Http\Actions\Widgets\CreateEventWidgetAction;
use HiEvents\Http\Actions\Widgets\CreateOrganizerWidgetAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Mcp\Tools\AddLineupPerformerTool;
use HiEvents\Mcp\Tools\CreateWidgetTool;
use HiEvents\Mcp\Tools\SetEventCoverImageTool;
use HiEvents\Mcp\Tools\Support\RemoteImageUploader;
use HiEvents\Mcp\Tools\UpdateEventPageThemeTool;
use HiEvents\Mcp\Tools\UpdateTicketDesignTool;
use Laravel\Mcp\Request;
use Mockery;
use Mockery\MockInterface;

class LineupWidgetAndDesignToolsTest extends McpToolTestCase
{
    private RemoteImageUploader&MockInterface $uploader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploader = Mockery::mock(RemoteImageUploader::class);
    }

    public function test_a_new_performer_gets_a_profile_on_the_events_organizer_with_its_photo(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateProfileAction::class, ['organizer_id' => 3], ['name' => 'The Ensemble', 'tagline' => 'Jazz'])
            ->andReturn($this->ok(['id' => 40]));
        $this->uploader->shouldReceive('upload')->once()->with('https://img.example.com/a.jpg', ImageType::PROFILE_IMAGE, 40)->andReturn($this->ok(['id' => 9]));
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateLineupEntryAction::class, ['event_id' => 12], ['profile_id' => 40, 'billing_tier' => 'HEADLINER', 'role_label' => null])
            ->andReturn($this->ok(['id' => 77, 'profile_id' => 40, 'billing_tier' => 'HEADLINER', 'profile' => ['name' => 'The Ensemble']]));

        $result = $this->structured((new AddLineupPerformerTool($this->api, $this->uploader))->handle(new Request([
            'event_id' => 12, 'name' => 'The Ensemble', 'tagline' => 'Jazz', 'billing_tier' => 'HEADLINER', 'image_url' => 'https://img.example.com/a.jpg',
        ])));

        $this->assertSame(77, $result['performer']['id']);
    }

    public function test_an_existing_profile_is_added_without_creating_another(): void
    {
        $this->api->shouldNotReceive('get');
        $this->uploader->shouldNotReceive('upload');
        $this->api->shouldReceive('send')
            ->once()
            ->with('POST', CreateLineupEntryAction::class, ['event_id' => 12], ['profile_id' => 40, 'billing_tier' => 'STANDARD', 'role_label' => 'Support'])
            ->andReturn($this->ok(['id' => 78, 'profile_id' => 40, 'billing_tier' => 'STANDARD']));

        $this->structured((new AddLineupPerformerTool($this->api, $this->uploader))->handle(new Request(['event_id' => 12, 'profile_id' => 40, 'role_label' => 'Support'])));
    }

    public function test_a_performer_needs_a_profile_or_a_name(): void
    {
        $this->api->shouldNotReceive('send');

        $this->errorText((new AddLineupPerformerTool($this->api, $this->uploader))->handle(new Request(['event_id' => 12])));
    }

    public function test_a_performer_cannot_mix_an_existing_profile_with_new_profile_details(): void
    {
        $this->api->shouldNotReceive('send');

        $this->errorText((new AddLineupPerformerTool($this->api, $this->uploader))->handle(new Request(['event_id' => 12, 'profile_id' => 40, 'image_url' => 'https://img.example.com/a.jpg'])));
        $this->errorText((new AddLineupPerformerTool($this->api, $this->uploader))->handle(new Request(['event_id' => 12, 'image_url' => 'https://img.example.com/a.jpg'])));
    }

    public function test_a_new_profile_is_removed_when_its_photo_cannot_be_fetched(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')->once()->with('POST', CreateProfileAction::class, ['organizer_id' => 3], ['name' => 'The Ensemble'])->andReturn($this->ok(['id' => 40]));
        $this->uploader->shouldReceive('upload')->once()->andThrow(new RemoteImageException('The image is larger than 5 MB.'));
        $this->api->shouldReceive('send')->once()->with('DELETE', DeleteProfileAction::class, ['organizer_id' => 3, 'profile_id' => 40])->andReturn($this->ok([]));
        $this->api->shouldNotReceive('send')->with('POST', CreateLineupEntryAction::class, Mockery::any(), Mockery::any());

        $this->assertSame('The image is larger than 5 MB.', $this->errorText((new AddLineupPerformerTool($this->api, $this->uploader))->handle(new Request([
            'event_id' => 12, 'name' => 'The Ensemble', 'image_url' => 'https://img.example.com/a.jpg',
        ]))));
    }

    public function test_a_new_profile_is_removed_when_the_lineup_entry_fails(): void
    {
        $this->api->shouldReceive('get')->with(GetEventAction::class, ['event_id' => 12])->andReturn($this->ok($this->event()));
        $this->api->shouldReceive('send')->once()->with('POST', CreateProfileAction::class, ['organizer_id' => 3], ['name' => 'The Ensemble'])->andReturn($this->ok(['id' => 40]));
        $this->api->shouldReceive('send')->once()->with('POST', CreateLineupEntryAction::class, ['event_id' => 12], Mockery::any())
            ->andReturn($this->failed(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY, ['errors' => ['role_label' => ['Too long.']]]));
        $this->api->shouldReceive('send')->once()->with('DELETE', DeleteProfileAction::class, ['organizer_id' => 3, 'profile_id' => 40])->andReturn($this->ok([]));

        $this->assertSame('role_label: Too long.', $this->errorText((new AddLineupPerformerTool($this->api, $this->uploader))->handle(new Request([
            'event_id' => 12, 'name' => 'The Ensemble',
        ]))));
    }

    public function test_widgets_go_to_the_event_or_organizer_route_by_type(): void
    {
        $this->api->shouldReceive('send')->once()->with('POST', CreateEventWidgetAction::class, ['event_id' => 12], [
            'name' => 'Buy button', 'type' => 'BUTTON', 'settings' => ['button_text' => 'Get tickets'],
        ])->andReturn($this->ok(['id' => 1, 'name' => 'Buy button', 'type' => 'BUTTON', 'short_id' => 'wgt_1', 'event_id' => 12, 'organizer_id' => 3, 'settings' => ['button_text' => 'Get tickets']]));
        $this->api->shouldReceive('send')->once()->with('POST', CreateOrganizerWidgetAction::class, ['organizer_id' => 3], [
            'name' => 'All events', 'type' => 'EVENTS', 'settings' => null,
        ])->andReturn($this->ok(['id' => 2, 'name' => 'All events', 'type' => 'EVENTS', 'short_id' => 'wgt_2', 'event_id' => null, 'organizer_id' => 3, 'settings' => []]));

        $tool = new CreateWidgetTool($this->api);

        $button = $this->structured($tool->handle(new Request(['event_id' => 12, 'type' => 'BUTTON', 'name' => 'Buy button', 'button_text' => 'Get tickets'])));
        $this->assertStringContainsString('data-hievents-button-text="Get tickets"', $button['widget']['embed_code']);

        $events = $this->structured($tool->handle(new Request(['organizer_id' => 3, 'type' => 'EVENTS', 'name' => 'All events'])));
        $this->assertStringContainsString('data-hievents-organizer-id="3"', $events['widget']['embed_code']);

        $this->errorText($tool->handle(new Request(['type' => 'LINEUP', 'name' => 'No event'])));
    }

    public function test_ticket_design_merges_into_the_saved_design_and_drops_an_invalid_layout(): void
    {
        $this->api->shouldReceive('get')->with(GetEventSettingsAction::class, ['event_id' => 12])->andReturn($this->ok([
            'ticket_design_settings' => ['enabled' => true, 'accent_color' => '#111111', 'footer_text' => 'Old', 'layout_type' => 'classic'],
        ]));
        $this->uploader->shouldReceive('upload')->once()->with('https://img.example.com/logo.png', ImageType::TICKET_LOGO, 12)->andReturn($this->ok(['id' => 55]));
        $this->api->shouldReceive('send')
            ->once()
            ->with('PATCH', PartialEditEventSettingsAction::class, ['event_id' => 12], [
                'ticket_design_settings' => ['enabled' => true, 'accent_color' => '#111111', 'footer_text' => 'Bring ID', 'logo_image_id' => 55],
            ])
            ->andReturn($this->ok([]));

        $this->structured((new UpdateTicketDesignTool($this->api, $this->uploader))->handle(new Request([
            'event_id' => 12, 'footer_text' => 'Bring ID', 'logo_url' => 'https://img.example.com/logo.png',
        ])));
    }

    public function test_page_theme_merges_and_mirrors_the_legacy_colour_fields(): void
    {
        $this->api->shouldReceive('get')->with(GetEventSettingsAction::class, ['event_id' => 12])->andReturn($this->ok([
            'homepage_theme_settings' => ['accent' => '#111111', 'background' => '#ffffff', 'mode' => 'light', 'layout' => 'MARQUEE', 'background_type' => 'COLOR'],
        ]));
        $this->api->shouldReceive('send')
            ->once()
            ->with('PATCH', PartialEditEventSettingsAction::class, ['event_id' => 12], [
                'homepage_theme_settings' => ['accent' => '#E11D48', 'background' => '#ffffff', 'mode' => 'dark', 'layout' => 'MARQUEE', 'background_type' => 'COLOR'],
                'homepage_primary_color' => '#E11D48',
                'homepage_body_background_color' => '#ffffff',
                'homepage_background_type' => 'COLOR',
            ])
            ->andReturn($this->ok([]));

        $this->structured((new UpdateEventPageThemeTool($this->api))->handle(new Request(['event_id' => 12, 'accent' => '#E11D48', 'mode' => 'dark'])));
    }

    public function test_a_cover_image_that_cannot_be_fetched_is_reported_without_an_upload(): void
    {
        $this->uploader->shouldReceive('upload')->andThrow(new RemoteImageException('The image URL must be a public https address.'));

        $error = $this->errorText((new SetEventCoverImageTool($this->api, $this->uploader))->handle(new Request([
            'event_id' => 12, 'image_url' => 'https://10.0.0.1/cover.jpg',
        ])));

        $this->assertSame('The image URL must be a public https address.', $error);
    }
}
