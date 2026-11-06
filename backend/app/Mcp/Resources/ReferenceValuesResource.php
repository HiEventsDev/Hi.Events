<?php

namespace HiEvents\Mcp\Resources;

use DateTimeZone;
use HiEvents\DomainObjects\Enums\EventCategory;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('reference_values')]
#[Title('Reference values')]
#[Description('The currencies, timezones and event categories Hi.Events accepts.')]
#[Uri('hievents://reference/values')]
#[MimeType('application/json')]
class ReferenceValuesResource extends Resource
{
    public function handle(Request $request): Response
    {
        $currencies = include base_path('data/currencies.php');

        return Response::json([
            'currencies' => array_values($currencies),
            'timezones' => DateTimeZone::listIdentifiers(),
            'event_categories' => EventCategory::valuesArray(),
        ]);
    }
}
