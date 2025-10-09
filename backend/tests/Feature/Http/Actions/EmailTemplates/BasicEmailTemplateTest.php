<?php

namespace Tests\Feature\Http\Actions\EmailTemplates;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BasicEmailTemplateTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create account configuration (required by auth system)
        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => [
                'percentage' => 1.5,
                'fixed' => 0,
            ]
        ]);

        // Create user with account
        $password = 'password123';
        $this->user = User::factory()->password($password)->withAccount()->create();

        // Login to get JWT token
        $loginResponse = $this->postJson('/auth/login', [
            'email' => $this->user->email,
            'password' => $password,
        ]);

        $this->authToken = $loginResponse->headers->get('X-Auth-Token');
    }

    public function test_token_endpoint_works(): void
    {
        $response = $this->getJson('/email-templates/tokens/order_confirmation', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonStructure([
                'tokens' => [
                    '*' => [
                        'token',
                        'description',
                        'example',
                    ],
                ],
            ]);

        $tokens = $response->json('tokens');
        $this->assertNotEmpty($tokens);
        $this->assertGreaterThan(5, count($tokens)); // Should have multiple tokens
    }

    public function test_token_endpoint_validates_template_type(): void
    {
        $response = $this->getJson('/email-templates/tokens/invalid_type', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_BAD_REQUEST);
    }

    public function test_endpoints_require_authentication(): void
    {
        // Test token endpoint without auth
        $response = $this->getJson('/email-templates/tokens/order_confirmation');
        $response->assertStatus(ResponseCodes::HTTP_INTERNAL_SERVER_ERROR);

        // Test organizer endpoint without auth (using dummy ID)
        $response = $this->getJson("/organizers/999/email-templates");
        $response->assertStatus(ResponseCodes::HTTP_INTERNAL_SERVER_ERROR);
    }
}
