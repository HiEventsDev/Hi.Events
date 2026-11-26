<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Licensing\Plan;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmLicensingTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    private ?User $superAdmin = null;

    private const UNGATED_WRITES = [
        'POST crm/contacts/{contact_id}/unsubscribe',
        'POST crm/campaigns/{campaign_id}/cancel',
        'POST crm/campaigns/{campaign_id}/end-early-access',
        'POST crm/contacts/export',
        'POST crm/contacts/bulk/unsubscribe',
        'POST crm/contacts/bulk/delete',
        'POST crm/automations/{automation_id}/disable',
        'POST crm/suppressions/lookup',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => false]);
        $this->setUpCrmApi();
    }

    public function test_routes_that_create_or_configure_carry_the_crm_licence_gate_and_reads_and_removals_do_not(): void
    {
        $gates = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(static fn (Route $route) => str_starts_with($route->uri(), 'organizers/{organizer_id}/crm/'))
            ->mapWithKeys(static fn (Route $route) => [
                implode('|', array_diff($route->methods(), ['HEAD'])).' '.substr($route->uri(), strlen('organizers/{organizer_id}/')) => in_array('ee.licensed:crm', $route->gatherMiddleware(), true),
            ]);

        $this->assertCount(count($this->crmRoutes()), $gates);
        foreach ($this->crmRoutes() as $name => [$method]) {
            $this->assertSame($this->isGated($name, $method), $gates[$name], $name);
        }
    }

    public function test_approving_a_campaign_needs_the_crm_licence_while_listing_and_rejecting_do_not(): void
    {
        $gates = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(static fn (Route $route) => str_starts_with($route->uri(), 'admin/crm/'))
            ->mapWithKeys(static fn (Route $route) => [
                implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri() => in_array('ee.licensed:crm', $route->gatherMiddleware(), true),
            ])
            ->all();

        $this->assertSame(
            [
                'GET admin/crm/campaigns/pending-review' => false,
                'POST admin/crm/campaigns/{campaign_id}/approve' => true,
                'POST admin/crm/campaigns/{campaign_id}/reject' => false,
            ],
            $gates,
        );
    }

    #[DataProvider('lapsedLicences')]
    public function test_without_a_crm_licence_a_pending_campaign_can_be_rejected_but_not_approved(string $licence): void
    {
        Bus::fake();
        $pendingId = $this->insertCampaign(['status' => 'PENDING_REVIEW', 'status_reason' => 'FIRST_CAMPAIGN']);
        $this->insertRecipient($pendingId, $this->contactId, 'ada@example.com');
        $headers = fn () => $this->memberHeaders($this->superAdmin ??= $this->addAccountUser($this->accountId, Role::SUPERADMIN));
        $this->lapse($licence);

        $this->json('POST', "/admin/crm/campaigns/$pendingId/approve", [], $headers())
            ->assertForbidden()
            ->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');
        $this->assertSame('PENDING_REVIEW', $this->campaign($pendingId)->status);
        Bus::assertNothingDispatched();

        $this->json('GET', '/admin/crm/campaigns/pending-review', [], $headers())->assertOk();
        $this->json('POST', "/admin/crm/campaigns/$pendingId/reject", [], $headers())->assertOk();
        $this->assertSame('REJECTED', $this->campaign($pendingId)->status);
        $this->assertSame(0, DB::table('crm_campaign_recipients')->where('campaign_id', $pendingId)->count());
    }

    public function test_the_expensive_crm_routes_are_rate_limited_under_their_own_names(): void
    {
        $throttles = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(static fn (Route $route) => str_starts_with($route->uri(), 'organizers/{organizer_id}/crm/'))
            ->mapWithKeys(static fn (Route $route) => [
                implode('|', array_diff($route->methods(), ['HEAD'])).' '.substr($route->uri(), strlen('organizers/{organizer_id}/')) => collect($route->gatherMiddleware())
                    ->first(static fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:') && substr_count($middleware, ',') === 2),
            ])
            ->filter()
            ->all();

        $this->assertEquals(
            [
                'POST crm/sync' => 'throttle:6,60,crm-sync',
                'POST crm/imports' => 'throttle:10,60,crm-contact-import',
                'POST crm/imports/suppressions/preview' => 'throttle:30,60,crm-import-preview',
                'POST crm/suppressions/lookup' => 'throttle:30,1,crm-suppression-lookup',
                'GET crm/contacts/export' => 'throttle:20,60,crm-contact-export',
                'POST crm/contacts/export' => 'throttle:20,60,crm-contact-export',
                'POST crm/contacts/bulk/tags' => 'throttle:30,60,crm-bulk',
                'POST crm/contacts/bulk/unsubscribe' => 'throttle:30,60,crm-bulk',
                'POST crm/contacts/bulk/delete' => 'throttle:30,60,crm-bulk',
                'POST crm/campaigns/preview' => 'throttle:120,1,crm-campaign-preview',
                'POST crm/campaigns/sms-preview' => 'throttle:120,1,crm-campaign-sms-preview',
                'POST crm/campaigns/{campaign_id}/send' => 'throttle:20,60,crm-campaign-send',
                'POST crm/campaigns/{campaign_id}/test' => 'throttle:10,60,crm-campaign-test',
                'POST crm/automations/preview' => 'throttle:120,1,crm-automation-preview',
                'POST crm/automations/test' => 'throttle:10,60,crm-automation-test',
                'POST crm/sms-connection/senders' => 'throttle:20,60,crm-sms-senders',
                'PUT crm/sms-connection' => 'throttle:20,60,crm-sms-connection',
                'POST crm/sms-connection/test' => 'throttle:10,60,crm-sms-test',
            ],
            $throttles,
        );
    }

    public function test_public_audience_routes_are_rate_limited_under_their_own_names(): void
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(static fn (Route $route) => str_starts_with($route->uri(), 'public/crm/') || $route->uri() === 'public/organizers/{organizer_id}/signup-form')
            ->keyBy(static fn (Route $route) => implode('|', array_diff($route->methods(), ['HEAD'])).' '.substr($route->uri(), strlen('public/')));

        $this->assertEquals(
            [
                'GET crm/forms/{public_id}' => 'throttle:300,1,crm-form-view',
                'POST crm/forms/{public_id}/submissions' => 'throttle:crm-form-submit',
                'GET organizers/{organizer_id}/signup-form' => 'throttle:300,1,crm-form-view',
                'GET crm/confirm/{token}' => 'throttle:60,1,crm-confirm-view',
                'POST crm/confirm/{token}' => 'throttle:30,1,crm-confirm',
                'GET crm/unsubscribe/{token}' => 'throttle:300,1,crm-unsubscribe-view',
                'POST crm/unsubscribe/{token}' => 'throttle:2000,1,crm-unsubscribe',
                'POST crm/unsubscribe/{token}/resubscribe' => 'throttle:30,1,crm-resubscribe',
            ],
            $routes->map(static fn (Route $route) => collect($route->gatherMiddleware())
                ->first(static fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:') && $middleware !== 'throttle:api'))->all(),
        );

        $withoutTheSharedApiLimit = $routes
            ->reject(static fn (Route $route) => in_array(ThrottleRequests::class.':api', RouteFacade::gatherRouteMiddleware($route), true))
            ->keys()
            ->all();
        $this->assertSame(['POST crm/unsubscribe/{token}'], $withoutTheSharedApiLimit);
    }

    public static function lapsedLicences(): array
    {
        return [
            'no licence key' => ['missing'],
            'expired licence' => ['expired'],
            'licence without the crm add-on' => ['own_events'],
        ];
    }

    #[DataProvider('lapsedLicences')]
    public function test_without_a_crm_licence_gated_routes_are_blocked_and_write_nothing(string $licence): void
    {
        Bus::fake();
        $this->lapse($licence);
        $before = $this->crmState();

        foreach ($this->crmRoutes() as $name => [$method, $path, $body]) {
            if (! $this->isGated($name, $method)) {
                continue;
            }

            $response = $this->crm($method, $path, $body, $this->ownerHeaders());
            $this->assertSame(403, $response->getStatusCode(), $name);
            $this->assertSame('FEATURE_UNAVAILABLE', $response->json('error_code'), $name);
        }

        $this->assertSame($before, $this->crmState());
        Bus::assertNothingBatched();
    }

    #[DataProvider('lapsedLicences')]
    public function test_without_a_crm_licence_existing_audience_data_can_still_be_read(string $licence): void
    {
        $this->lapse($licence);

        $statuses = [];
        foreach ($this->crmRoutes() as $name => [$method, $path, $body]) {
            if ($method === 'GET') {
                $statuses[$name] = $this->crm($method, $path, $body, $this->ownerHeaders())->getStatusCode();
            }
        }

        $this->assertSame(array_fill_keys(array_keys($statuses), 200), $statuses);
    }

    #[DataProvider('lapsedLicences')]
    public function test_without_a_crm_licence_data_can_still_be_removed_and_contacts_unsubscribed(string $licence): void
    {
        $this->lapse($licence);
        $headers = fn () => $this->ownerHeaders();

        $this->crm('POST', "contacts/{$this->contactId}/unsubscribe", [], $headers())
            ->assertOk()
            ->assertJsonPath('data.email_marketing_status', 'UNSUBSCRIBED');
        $this->crm('DELETE', "contacts/{$this->contactId}/notes/{$this->noteId}", [], $headers())->assertNoContent();
        $this->crm('DELETE', "tags/{$this->tagId}", [], $headers())->assertNoContent();
        $this->crm('DELETE', "segments/{$this->segmentId}", [], $headers())->assertNoContent();
        $this->crm('DELETE', "contacts/{$this->contactId}", [], $headers())->assertNoContent();

        $this->assertSame(0, DB::table('crm_contacts')->where('id', $this->contactId)->count());
        $this->assertSame(2, DB::table('crm_deleted_contacts')->where('organizer_id', $this->organizerId)->count());
    }

    public function test_a_self_hosted_licence_that_includes_crm_needs_no_feature_flag(): void
    {
        $this->useLicence(expiresAt: '2099-01-01');
        DB::table('account_feature_flag_overrides')->where('account_id', $this->accountId)->delete();
        $this->setFeatureFlagDefault(FeatureFlag::CRM, false);

        $this->crm('GET', 'contacts', [], $this->ownerHeaders())->assertOk();
        $this->crm('POST', 'tags', ['name' => 'Press'], $this->ownerHeaders())->assertCreated();
    }

    public function test_on_the_cloud_licence_the_feature_flag_decides(): void
    {
        $this->useCloudLicence();
        DB::table('account_feature_flag_overrides')->where('account_id', $this->accountId)->delete();
        $this->setFeatureFlagDefault(FeatureFlag::CRM, false);

        $this->crm('GET', 'contacts', [], $this->ownerHeaders())->assertOk();
        $this->crm('POST', 'tags', ['name' => 'Press'], $this->ownerHeaders())->assertForbidden()->assertJsonPath('error_code', 'FEATURE_UNAVAILABLE');

        $this->setFeatureFlagDefault(FeatureFlag::CRM, true);

        $this->crm('GET', 'contacts', [], $this->ownerHeaders())->assertOk();
        $this->crm('POST', 'tags', ['name' => 'Press'], $this->ownerHeaders())->assertCreated();
    }

    #[DataProvider('audienceUsage')]
    public function test_the_audience_counts_as_in_use_only_while_it_has_contacts_live_automations_forms_or_recent_campaigns(string $table, array $attributes, bool $inUse): void
    {
        $repository = app(CrmOrganizerStateRepositoryInterface::class);
        DB::table('crm_campaigns')->where('account_id', $this->accountId)->delete();
        DB::table('crm_contacts')->where('account_id', $this->accountId)->delete();
        DB::table('crm_automations')->where('account_id', $this->accountId)->delete();
        DB::table('crm_signup_forms')->where('account_id', $this->accountId)->delete();
        $this->assertFalse($repository->isAudienceInUse($this->accountId, now()));
        $attributes = array_map(static fn (mixed $value) => is_string($value) && str_starts_with($value, 'days ago:') ? now()->subDays((int) substr($value, 9)) : $value, $attributes);

        match ($table) {
            'crm_campaigns' => $this->insertCampaign($attributes),
            'crm_contacts' => $this->insertContact('built@example.com', $attributes),
            'crm_signup_forms' => $this->insertSignupForm($this->organizerId, $attributes),
            'crm_automations' => DB::table('crm_automations')->insert([
                'account_id' => $this->accountId,
                'organizer_id' => $this->organizerId,
                'trigger' => 'WELCOME',
                'subject' => 'Welcome',
                'body' => '<p>Welcome</p>',
                'created_at' => now(),
                'updated_at' => now(),
                ...$attributes,
            ]),
        };

        $this->assertSame($inUse, $repository->isAudienceInUse($this->accountId, now()));
        $this->assertSame($inUse, $repository->isAudienceInUse(null, now()));
        $this->assertFalse($repository->isAudienceInUse($this->otherAccountId(), now()));
    }

    public static function audienceUsage(): array
    {
        return [
            'a contact' => ['crm_contacts', [], true],
            'an unsubscribed contact' => ['crm_contacts', ['email_marketing_status' => 'UNSUBSCRIBED'], true],
            'automation switched on' => ['crm_automations', ['is_enabled' => true], true],
            'automation switched off' => ['crm_automations', ['is_enabled' => false], false],
            'automation switched on but deleted' => ['crm_automations', ['is_enabled' => true, 'deleted_at' => 'days ago:1'], false],
            'active signup form' => ['crm_signup_forms', ['status' => 'ACTIVE'], true],
            'paused signup form' => ['crm_signup_forms', ['status' => 'PAUSED'], false],
            'deleted signup form' => ['crm_signup_forms', ['status' => 'ACTIVE', 'deleted_at' => 'days ago:1'], false],
            'draft campaign' => ['crm_campaigns', ['status' => 'DRAFT'], false],
            'scheduled campaign' => ['crm_campaigns', ['status' => 'SCHEDULED'], true],
            'campaign waiting for review' => ['crm_campaigns', ['status' => 'PENDING_REVIEW'], true],
            'campaign being sent' => ['crm_campaigns', ['status' => 'SENDING', 'launched_at' => 'days ago:40'], true],
            'campaign sent 29 days ago' => ['crm_campaigns', ['status' => 'SENT', 'launched_at' => 'days ago:29'], true],
            'campaign sent 31 days ago' => ['crm_campaigns', ['status' => 'SENT', 'launched_at' => 'days ago:31'], false],
        ];
    }

    private function otherAccountId(): int
    {
        return $this->createAccountOwner()[1];
    }

    private function isGated(string $name, string $method): bool
    {
        return ! in_array($method, ['GET', 'DELETE'], true) && ! in_array($name, self::UNGATED_WRITES, true);
    }

    private function lapse(string $licence): void
    {
        match ($licence) {
            'missing' => $this->useLicenceKey(null),
            'expired' => $this->useLicence(expiresAt: '2020-01-01'),
            'own_events' => $this->useLicence(expiresAt: '2099-01-01', plan: Plan::OWN_EVENTS),
        };
    }
}
