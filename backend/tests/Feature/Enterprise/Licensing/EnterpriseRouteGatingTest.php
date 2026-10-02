<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Licensing;

use HiEvents\Enterprise\Licensing\LicensedFeature;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class EnterpriseRouteGatingTest extends TestCase
{
    private const UNGATED_ENTERPRISE_ROUTES = [
        'DELETE events/{event_id}/box-offices/{box_office_id}',
        'DELETE events/{event_id}/seat-map',
        'DELETE organizers/{organizerId}/stripe/terminal/readers/{readerId}',
        'DELETE organizers/{organizer_id}/seat-maps/{seat_map_id}',
        'DELETE public/box-offices/{box_office_short_id}/sessions/current',
        'GET admin/licence',
        'GET events/{event_id}/box-offices',
        'GET events/{event_id}/box-offices/{box_office_id}',
        'GET events/{event_id}/box-offices/{box_office_id}/stats',
        'GET events/{event_id}/occurrences/{occurrence_id}/occupied-seats',
        'GET events/{event_id}/seat-map',
        'GET organizers/{organizerId}/stripe/terminal/readers',
        'GET organizers/{organizer_id}/seat-maps',
        'GET organizers/{organizer_id}/seat-maps/{seat_map_id}',
        'GET public/box-offices/{box_office_short_id}',
        'GET public/box-offices/{box_office_short_id}/occupied-seats',
        'GET public/box-offices/{box_office_short_id}/orders',
        'GET public/box-offices/{box_office_short_id}/orders/{order_short_id}',
        'GET public/box-offices/{box_office_short_id}/products',
        'GET public/box-offices/{box_office_short_id}/seat-map',
        'GET public/compliance',
        'GET public/events/{event_id}/occurrences/{occurrence_id}/best-available-seats',
        'GET public/events/{event_id}/occurrences/{occurrence_id}/seat-availability',
        'GET public/events/{event_id}/seat-map',
        'GET public/instance',
        'POST events/{event_id}/seat-blocks/release',
        'POST public/box-offices/{box_office_short_id}/best-available-seats',
        'POST public/box-offices/{box_office_short_id}/orders',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/abandon',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/cancel',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/card',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/card/cancel-action',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/resend-confirmation',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/tender',
        'PUT events/{event_id}/attendees/{attendee_id}/seat',
        'PUT public/box-offices/{box_office_short_id}/sessions/current',
        'PUT public/events/{event_id}/order/{order_short_id}/attendees/{attendee_short_id}/seat',
    ];

    public function test_every_ungated_enterprise_route_is_a_deliberate_exception(): void
    {
        $ungated = [];
        foreach ($this->enterpriseRoutes() as $route) {
            if ($this->licenceGates($route) === []) {
                $ungated[] = implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri();
            }
        }
        sort($ungated);

        $this->assertSame(self::UNGATED_ENTERPRISE_ROUTES, $ungated);
    }

    public function test_every_licence_gate_names_a_licensed_feature(): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            foreach ($this->licenceGates($route) as $feature) {
                $this->assertNotNull(LicensedFeature::tryFrom($feature), "{$route->uri()} gates on unknown feature [$feature]");
            }
        }
    }

    /**
     * @return Route[]
     */
    private function enterpriseRoutes(): array
    {
        return array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            static fn (Route $route) => str_starts_with($route->getActionName(), 'HiEvents\\Enterprise\\'),
        );
    }

    /**
     * @return string[]
     */
    private function licenceGates(Route $route): array
    {
        return collect($route->gatherMiddleware())
            ->filter(static fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'ee.licensed:'))
            ->map(static fn (string $middleware) => substr($middleware, strlen('ee.licensed:')))
            ->values()
            ->all();
    }
}
