<?php

namespace HiEvents\Mcp\Servers;

use HiEvents\Mcp\Resources\ReferenceValuesResource;
use HiEvents\Mcp\Tools\AddEventDateTool;
use HiEvents\Mcp\Tools\AddLineupPerformerTool;
use HiEvents\Mcp\Tools\AddLineupSpaceTool;
use HiEvents\Mcp\Tools\AddScheduleItemTool;
use HiEvents\Mcp\Tools\CreateAddonTool;
use HiEvents\Mcp\Tools\CreateEventTool;
use HiEvents\Mcp\Tools\CreateOrganizerTool;
use HiEvents\Mcp\Tools\CreatePromoCodeTool;
use HiEvents\Mcp\Tools\CreateQuestionTool;
use HiEvents\Mcp\Tools\CreateSharedCapacityTool;
use HiEvents\Mcp\Tools\CreateTicketCategoryTool;
use HiEvents\Mcp\Tools\CreateTicketTool;
use HiEvents\Mcp\Tools\CreateWidgetTool;
use HiEvents\Mcp\Tools\DeleteQuestionTool;
use HiEvents\Mcp\Tools\GenerateEventDatesTool;
use HiEvents\Mcp\Tools\GetDateGenerationStatusTool;
use HiEvents\Mcp\Tools\GetEventReportTool;
use HiEvents\Mcp\Tools\GetEventStatsTool;
use HiEvents\Mcp\Tools\GetEventTool;
use HiEvents\Mcp\Tools\GetLineupTool;
use HiEvents\Mcp\Tools\GetOrganizerReportTool;
use HiEvents\Mcp\Tools\GetOrganizerStatsTool;
use HiEvents\Mcp\Tools\ListEventDatesTool;
use HiEvents\Mcp\Tools\ListEventsTool;
use HiEvents\Mcp\Tools\ListOrganizersTool;
use HiEvents\Mcp\Tools\ListPerformerProfilesTool;
use HiEvents\Mcp\Tools\ListPromoCodesTool;
use HiEvents\Mcp\Tools\ListQuestionsTool;
use HiEvents\Mcp\Tools\ListSharedCapacitiesTool;
use HiEvents\Mcp\Tools\ListTicketCategoriesTool;
use HiEvents\Mcp\Tools\ListTicketsTool;
use HiEvents\Mcp\Tools\ListVenuesTool;
use HiEvents\Mcp\Tools\ListWidgetsTool;
use HiEvents\Mcp\Tools\PublishEventTool;
use HiEvents\Mcp\Tools\SetEventCoverImageTool;
use HiEvents\Mcp\Tools\SetEventVenueTool;
use HiEvents\Mcp\Tools\UnpublishEventTool;
use HiEvents\Mcp\Tools\UpdateEventPageThemeTool;
use HiEvents\Mcp\Tools\UpdateEventSettingsTool;
use HiEvents\Mcp\Tools\UpdateEventTool;
use HiEvents\Mcp\Tools\UpdatePromoCodeTool;
use HiEvents\Mcp\Tools\UpdateQuestionTool;
use HiEvents\Mcp\Tools\UpdateTicketDesignTool;
use HiEvents\Mcp\Tools\UpdateTicketTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Hi.Events')]
#[Version('1.0.0')]
#[Instructions('Hi.Events is an event management and ticketing platform. An account has organizers; each organizer has events; an event has dates, tickets and an optional venue. New events are drafts until published. Dates you send are local to the event timezone (YYYY-MM-DD HH:MM); dates returned are ISO 8601 with the event timezone offset. Prices are decimal amounts in the event currency.')]
class HiEventsServer extends Server
{
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        ListOrganizersTool::class,
        CreateOrganizerTool::class,
        ListEventsTool::class,
        GetEventTool::class,
        CreateEventTool::class,
        UpdateEventTool::class,
        SetEventCoverImageTool::class,
        ListEventDatesTool::class,
        AddEventDateTool::class,
        GenerateEventDatesTool::class,
        GetDateGenerationStatusTool::class,
        ListVenuesTool::class,
        SetEventVenueTool::class,
        ListTicketsTool::class,
        CreateTicketTool::class,
        UpdateTicketTool::class,
        CreateAddonTool::class,
        ListTicketCategoriesTool::class,
        CreateTicketCategoryTool::class,
        ListSharedCapacitiesTool::class,
        CreateSharedCapacityTool::class,
        ListPromoCodesTool::class,
        CreatePromoCodeTool::class,
        UpdatePromoCodeTool::class,
        ListQuestionsTool::class,
        CreateQuestionTool::class,
        UpdateQuestionTool::class,
        DeleteQuestionTool::class,
        GetLineupTool::class,
        ListPerformerProfilesTool::class,
        AddLineupPerformerTool::class,
        AddLineupSpaceTool::class,
        AddScheduleItemTool::class,
        UpdateEventSettingsTool::class,
        UpdateTicketDesignTool::class,
        UpdateEventPageThemeTool::class,
        ListWidgetsTool::class,
        CreateWidgetTool::class,
        GetEventStatsTool::class,
        GetOrganizerStatsTool::class,
        GetEventReportTool::class,
        GetOrganizerReportTool::class,
        PublishEventTool::class,
        UnpublishEventTool::class,
    ];

    protected array $resources = [
        ReferenceValuesResource::class,
    ];
}
