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
        'DELETE accounts/{account_id}/sso/connection',
        'DELETE accounts/{account_id}/sso/domains/{domain_id}',
        'DELETE events/{event_id}/box-offices/{box_office_id}',
        'DELETE events/{event_id}/seat-map',
        'DELETE organizers/{organizerId}/stripe/terminal/readers/{readerId}',
        'DELETE organizers/{organizer_id}/crm/automations/{automation_id}',
        'DELETE organizers/{organizer_id}/crm/campaigns/{campaign_id}',
        'DELETE organizers/{organizer_id}/crm/contacts/{contact_id}',
        'DELETE organizers/{organizer_id}/crm/contacts/{contact_id}/notes/{note_id}',
        'DELETE organizers/{organizer_id}/crm/forms/{form_id}',
        'DELETE organizers/{organizer_id}/crm/segments/{segment_id}',
        'DELETE organizers/{organizer_id}/crm/sms-connection',
        'DELETE organizers/{organizer_id}/crm/tags/{tag_id}',
        'DELETE organizers/{organizer_id}/crm/templates/{template_id}',
        'DELETE organizers/{organizer_id}/seat-maps/{seat_map_id}',
        'DELETE public/box-offices/{box_office_short_id}/sessions/current',
        'GET accounts/{account_id}/sso',
        'GET accounts/{account_id}/sso/enforcement',
        'GET admin/crm/campaigns/pending-review',
        'GET admin/licence',
        'GET admin/licence/sales',
        'GET events/{event_id}/box-offices',
        'GET events/{event_id}/box-offices/{box_office_id}',
        'GET events/{event_id}/box-offices/{box_office_id}/stats',
        'GET events/{event_id}/occurrences/{occurrence_id}/occupied-seats',
        'GET events/{event_id}/seat-map',
        'GET organizers/{organizerId}/stripe/terminal/readers',
        'GET organizers/{organizer_id}/crm/automations',
        'GET organizers/{organizer_id}/crm/automations/templates',
        'GET organizers/{organizer_id}/crm/bulk-operations/{operation_id}',
        'GET organizers/{organizer_id}/crm/campaigns',
        'GET organizers/{organizer_id}/crm/campaigns/{campaign_id}',
        'GET organizers/{organizer_id}/crm/campaigns/{campaign_id}/preview',
        'GET organizers/{organizer_id}/crm/campaigns/{campaign_id}/recipients',
        'GET organizers/{organizer_id}/crm/campaigns/{campaign_id}/send-check',
        'GET organizers/{organizer_id}/crm/contacts',
        'GET organizers/{organizer_id}/crm/contacts/export',
        'GET organizers/{organizer_id}/crm/contacts/{contact_id}',
        'GET organizers/{organizer_id}/crm/contacts/{contact_id}/activities',
        'GET organizers/{organizer_id}/crm/contacts/{contact_id}/consent',
        'GET organizers/{organizer_id}/crm/contacts/{contact_id}/notes',
        'GET organizers/{organizer_id}/crm/forms',
        'GET organizers/{organizer_id}/crm/imports',
        'GET organizers/{organizer_id}/crm/insights/events/{event_id}',
        'GET organizers/{organizer_id}/crm/insights/lead-time',
        'GET organizers/{organizer_id}/crm/insights/overview',
        'GET organizers/{organizer_id}/crm/insights/retention',
        'GET organizers/{organizer_id}/crm/segment-options',
        'GET organizers/{organizer_id}/crm/segments',
        'GET organizers/{organizer_id}/crm/segments/count',
        'GET organizers/{organizer_id}/crm/sms-connection',
        'GET organizers/{organizer_id}/crm/suppressions/summary',
        'GET organizers/{organizer_id}/crm/sync',
        'GET organizers/{organizer_id}/crm/tags',
        'GET organizers/{organizer_id}/crm/templates',
        'GET organizers/{organizer_id}/seat-maps',
        'GET organizers/{organizer_id}/seat-maps/{seat_map_id}',
        'GET public/box-offices/{box_office_short_id}',
        'GET public/box-offices/{box_office_short_id}/occupied-seats',
        'GET public/box-offices/{box_office_short_id}/orders',
        'GET public/box-offices/{box_office_short_id}/orders/{order_short_id}',
        'GET public/box-offices/{box_office_short_id}/products',
        'GET public/box-offices/{box_office_short_id}/seat-map',
        'GET public/compliance',
        'GET public/crm/confirm/{token}',
        'GET public/crm/forms/{public_id}',
        'GET public/crm/unsubscribe/{token}',
        'GET public/events/{event_id}/occurrences/{occurrence_id}/best-available-seats',
        'GET public/events/{event_id}/occurrences/{occurrence_id}/seat-availability',
        'GET public/events/{event_id}/seat-map',
        'GET public/instance',
        'GET public/organizers/{organizer_id}/signup-form',
        'POST admin/crm/campaigns/{campaign_id}/reject',
        'POST auth/sso/discover',
        'POST events/{event_id}/seat-blocks/release',
        'POST organizers/{organizer_id}/crm/automations/{automation_id}/disable',
        'POST organizers/{organizer_id}/crm/campaigns/{campaign_id}/cancel',
        'POST organizers/{organizer_id}/crm/campaigns/{campaign_id}/end-early-access',
        'POST organizers/{organizer_id}/crm/contacts/bulk/delete',
        'POST organizers/{organizer_id}/crm/contacts/bulk/unsubscribe',
        'POST organizers/{organizer_id}/crm/contacts/export',
        'POST organizers/{organizer_id}/crm/contacts/{contact_id}/unsubscribe',
        'POST organizers/{organizer_id}/crm/suppressions/lookup',
        'POST public/box-offices/{box_office_short_id}/best-available-seats',
        'POST public/box-offices/{box_office_short_id}/orders',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/abandon',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/cancel',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/card',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/card/cancel-action',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/resend-confirmation',
        'POST public/box-offices/{box_office_short_id}/orders/{order_short_id}/tender',
        'POST public/crm/confirm/{token}',
        'POST public/crm/forms/{public_id}/submissions',
        'POST public/crm/unsubscribe/{token}',
        'POST public/crm/unsubscribe/{token}/resubscribe',
        'POST public/webhooks/sms/twilio/{public_id}/{kind}',
        'PUT accounts/{account_id}/sso/connection/status',
        'PUT accounts/{account_id}/sso/enforcement',
        'PUT accounts/{account_id}/sso/members/{account_user_id}',
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
