<?php

namespace Tests\Feature\Http\Actions\Admin\Configurations;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class UpdateConfigurationActionTest extends TestCase
{
    use DatabaseTransactions;

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        DB::table('account_users')->where('user_id', $user->id)->update(['role' => 'SUPERADMIN']);

        $this->authToken = JWTAuth::claims(['account_id' => $accountId])->fromUser($user);
    }

    public function test_currency_default_configuration_rejects_mismatched_fee_currency(): void
    {
        $configurationId = $this->insertConfiguration(defaultForCurrency: 'USD');

        $response = $this->putJson(
            "/admin/configurations/{$configurationId}",
            $this->payload(currency: 'EUR'),
            $this->authHeaders(),
        );

        $response->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['application_fees.currency']);
    }

    public function test_currency_default_configuration_accepts_matching_fee_currency(): void
    {
        $configurationId = $this->insertConfiguration(defaultForCurrency: 'USD');

        $response = $this->putJson(
            "/admin/configurations/{$configurationId}",
            $this->payload(currency: 'USD'),
            $this->authHeaders(),
        );

        $response->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame('USD', $response->json('data.application_fees.currency'));
    }

    public function test_regular_configuration_allows_any_fee_currency(): void
    {
        $configurationId = $this->insertConfiguration(defaultForCurrency: null);

        $response = $this->putJson(
            "/admin/configurations/{$configurationId}",
            $this->payload(currency: 'EUR'),
            $this->authHeaders(),
        );

        $response->assertStatus(ResponseCodes::HTTP_OK);
        $this->assertSame('EUR', $response->json('data.application_fees.currency'));
    }

    private function insertConfiguration(?string $defaultForCurrency): int
    {
        return DB::table('organizer_configurations')->insertGetId([
            'name' => 'Configuration Under Test',
            'is_system_default' => false,
            'application_fees' => json_encode(['percentage' => 1.25, 'fixed' => 0.60, 'currency' => 'USD']),
            'default_for_currency' => $defaultForCurrency,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function payload(string $currency): array
    {
        return [
            'name' => 'Configuration Under Test',
            'application_fees' => [
                'fixed' => 0.60,
                'percentage' => 1.25,
                'currency' => $currency,
            ],
        ];
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
