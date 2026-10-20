<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->eventId = $this->createEvent($this->accountId, $this->organizerId);
    }

    public static function viewerWrites(): array
    {
        return [
            'create product' => ['post', '/events/{event}/products'],
            'update event' => ['put', '/events/{event}'],
            'update event status' => ['put', '/events/{event}/status'],
            'update event settings' => ['put', '/events/{event}/settings'],
            'create attendee' => ['post', '/events/{event}/attendees'],
            'create promo code' => ['post', '/events/{event}/promo-codes'],
            'create question' => ['post', '/events/{event}/questions'],
            'create check-in list' => ['post', '/events/{event}/check-in-lists'],
            'create capacity assignment' => ['post', '/events/{event}/capacity-assignments'],
            'create affiliate' => ['post', '/events/{event}/affiliates'],
            'send message' => ['post', '/events/{event}/messages'],
            'duplicate event' => ['post', '/events/{event}/duplicate'],
            'create event' => ['post', '/events'],
            'update organizer' => ['post', '/organizers/{organizer}'],
            'create location' => ['post', '/organizers/{organizer}/locations'],
            'create organizer' => ['post', '/organizers'],
        ];
    }

    #[DataProvider('viewerWrites')]
    public function test_viewers_cannot_write(string $method, string $path): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->json($method, $this->path($path), [], $this->headers($viewer))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'READ_ONLY_ROLE');
    }

    public static function viewerReads(): array
    {
        return [
            'event' => ['/events/{event}'],
            'products' => ['/events/{event}/products'],
            'orders' => ['/events/{event}/orders'],
            'attendees' => ['/events/{event}/attendees'],
            'promo codes' => ['/events/{event}/promo-codes'],
            'questions' => ['/events/{event}/questions'],
            'check-in lists' => ['/events/{event}/check-in-lists'],
            'capacity assignments' => ['/events/{event}/capacity-assignments'],
            'affiliates' => ['/events/{event}/affiliates'],
            'messages' => ['/events/{event}/messages'],
            'event stats' => ['/events/{event}/stats'],
            'organizer' => ['/organizers/{organizer}'],
            'organizer events' => ['/organizers/{organizer}/events'],
            'organizer stats' => ['/organizers/{organizer}/stats'],
            'locations' => ['/organizers/{organizer}/locations'],
        ];
    }

    #[DataProvider('viewerReads')]
    public function test_viewers_can_read(string $path): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->getJson($this->path($path), $this->headers($viewer))->assertOk();
    }

    public static function financialEndpoints(): array
    {
        return [
            'event report' => ['get', '/events/{event}/reports/daily_sales_report'],
            'organizer revenue report' => ['get', '/organizers/{organizer}/reports/revenue_summary'],
            'organizer tax report' => ['get', '/organizers/{organizer}/reports/tax_summary'],
            'order export' => ['post', '/events/{event}/orders/export'],
            'affiliate export' => ['post', '/events/{event}/affiliates/export'],
            'product purchase export' => ['post', '/events/{event}/products/purchases/export'],
            'event webhooks' => ['get', '/events/{event}/webhooks'],
            'create event webhook' => ['post', '/events/{event}/webhooks'],
            'organizer webhooks' => ['get', '/organizers/{organizer}/webhooks'],
            'platform fee preview' => ['get', '/events/{event}/settings/platform-fee-preview'],
            'vat settings' => ['get', '/organizers/{organizer}/vat-settings'],
        ];
    }

    #[DataProvider('financialEndpoints')]
    public function test_members_without_financials_are_refused_financial_endpoints(string $method, string $path): void
    {
        $member = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($member, $this->accountId, ['VIEW_FINANCIALS' => false]);

        $this->json($method, $this->path($path), [], $this->headers($member))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
    }

    public static function adminOnlyEndpoints(): array
    {
        return [
            'list users' => ['get', '/users'],
            'invite user' => ['post', '/users'],
            'update account' => ['put', '/accounts/{account}'],
            'delete event' => ['delete', '/events/{event}'],
            'delete organizer' => ['delete', '/organizers/{organizer}'],
            'stripe accounts' => ['get', '/organizers/{organizer}/stripe/connect_accounts'],
            'event team' => ['get', '/events/{event}/team'],
            'organizer team' => ['get', '/organizers/{organizer}/team'],
            'account deletion status' => ['get', '/accounts/deletion-request'],
        ];
    }

    #[DataProvider('adminOnlyEndpoints')]
    public function test_organizers_are_refused_admin_endpoints(string $method, string $path): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->json($method, $this->path($path), [], $this->headers($organizer))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
    }

    private function path(string $path): string
    {
        return str_replace(
            ['{event}', '{organizer}', '{account}'],
            [$this->eventId, $this->organizerId, $this->accountId],
            $path,
        );
    }

    private function headers(User $user): array
    {
        return $this->headersFor($user, $this->accountId);
    }
}
