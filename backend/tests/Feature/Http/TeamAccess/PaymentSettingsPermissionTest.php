<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Enterprise\BoxOffice\Http\Actions\Organizers\Stripe\Terminal\DeleteStripeTerminalReaderAction;
use HiEvents\Enterprise\BoxOffice\Http\Actions\Organizers\Stripe\Terminal\GetStripeTerminalReadersAction;
use HiEvents\Enterprise\BoxOffice\Http\Actions\Organizers\Stripe\Terminal\RegisterStripeTerminalReaderAction;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal\DeleteStripeTerminalReaderHandler;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal\GetStripeTerminalReadersHandler;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Organizer\Payment\Stripe\Terminal\RegisterStripeTerminalReaderHandler;
use HiEvents\Http\Actions\Organizers\Stripe\CopyStripeConnectAccountAction;
use HiEvents\Http\Actions\Organizers\Stripe\CreateStripeConnectAccountAction;
use HiEvents\Http\Actions\Organizers\Stripe\DisconnectStripeConnectAccountAction;
use HiEvents\Http\Actions\Organizers\Stripe\GetStripeConnectAccountsAction;
use HiEvents\Http\Actions\Organizers\Vat\UpsertOrganizerVatSettingAction;
use HiEvents\Http\Attributes\RequiresPermission;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\CopyStripeConnectAccountHandler;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\CreateStripeConnectAccountHandler;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DisconnectStripeConnectAccountHandler;
use HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\GetStripeConnectAccountsHandler;
use HiEvents\Services\Application\Handlers\Organizer\Vat\UpsertOrganizerVatSettingHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class PaymentSettingsPermissionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private const PAYMENT_SETTINGS_ACTIONS = [
        CopyStripeConnectAccountAction::class,
        CreateStripeConnectAccountAction::class,
        DeleteStripeTerminalReaderAction::class,
        DisconnectStripeConnectAccountAction::class,
        GetStripeConnectAccountsAction::class,
        GetStripeTerminalReadersAction::class,
        RegisterStripeTerminalReaderAction::class,
        UpsertOrganizerVatSettingAction::class,
    ];

    private const PAYMENT_SETTINGS_HANDLERS = [
        CopyStripeConnectAccountHandler::class,
        CreateStripeConnectAccountHandler::class,
        DeleteStripeTerminalReaderHandler::class,
        DisconnectStripeConnectAccountHandler::class,
        GetStripeConnectAccountsHandler::class,
        GetStripeTerminalReadersHandler::class,
        RegisterStripeTerminalReaderHandler::class,
        UpsertOrganizerVatSettingHandler::class,
    ];

    private int $accountId;

    private int $organizerId;

    private int $otherOrganizerId;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->otherOrganizerId = $this->createOrganizer($this->accountId);
    }

    public function test_exactly_the_payment_settings_actions_require_manage_payments(): void
    {
        $gated = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn (Route $route) => $route->getControllerClass())
            ->filter()
            ->unique()
            ->filter(fn (string $action) => collect((new ReflectionClass($action))->getAttributes(RequiresPermission::class))
                ->flatMap(fn ($attribute) => $attribute->newInstance()->permissions)
                ->contains(Permission::MANAGE_PAYMENTS))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(collect(self::PAYMENT_SETTINGS_ACTIONS)->sort()->values()->all(), $gated);
    }

    public static function paymentSettingsEndpoints(): array
    {
        return [
            'connect stripe' => ['post', '/organizers/{organizer}/stripe/connect'],
            'copy stripe account' => ['post', '/organizers/{organizer}/stripe/copy_from/{other}'],
            'disconnect stripe' => ['delete', '/organizers/{organizer}/stripe/connect_accounts/acct_1'],
            'list stripe accounts' => ['get', '/organizers/{organizer}/stripe/connect_accounts'],
            'upsert vat settings' => ['post', '/organizers/{organizer}/vat-settings'],
            'list terminal readers' => ['get', '/organizers/{organizer}/stripe/terminal/readers'],
            'register terminal reader' => ['post', '/organizers/{organizer}/stripe/terminal/readers'],
            'delete terminal reader' => ['delete', '/organizers/{organizer}/stripe/terminal/readers/1'],
        ];
    }

    #[DataProvider('paymentSettingsEndpoints')]
    public function test_organizers_cannot_change_where_money_is_paid_out(string $method, string $path): void
    {
        foreach (self::PAYMENT_SETTINGS_HANDLERS as $handler) {
            $this->mock($handler, fn (MockInterface $mock) => $mock->shouldNotReceive('handle'));
        }

        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->json($method, $this->path($path), [], $this->headersFor($organizer, $this->accountId))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
    }

    public function test_admins_pass_the_payment_settings_gate(): void
    {
        $this->mock(DisconnectStripeConnectAccountHandler::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('handle')
            ->once()
            ->with($this->organizerId, $this->accountId, 'acct_1'));

        $admin = $this->addAccountUser($this->accountId, Role::ADMIN);

        $this->deleteJson($this->path('/organizers/{organizer}/stripe/connect_accounts/acct_1'), [], $this->headersFor($admin, $this->accountId))
            ->assertNoContent();
    }

    private function path(string $path): string
    {
        return str_replace(['{organizer}', '{other}'], [$this->organizerId, $this->otherOrganizerId], $path);
    }
}
