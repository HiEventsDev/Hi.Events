<?php

namespace Tests\Feature\Http\Actions\EmailTemplates;

use HiEvents\Http\ResponseCodes;
use HiEvents\Models\Account;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EmailTemplateTokenTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Account $account;
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
        
        // Get the account created by withAccount()
        $this->account = $this->user->accounts()->first();

        // Login to get JWT token
        $loginResponse = $this->postJson('/auth/login', [
            'email' => $this->user->email,
            'password' => $password,
        ]);
        
        $this->authToken = $loginResponse->headers->get('X-Auth-Token');
    }

    public function test_can_get_order_confirmation_tokens(): void
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

        $firstNameToken = collect($tokens)->firstWhere('token', '{{ order_first_name }}');
        $this->assertNotNull($firstNameToken);
        $this->assertEquals('The first name of the person who placed the order', $firstNameToken['description']);

        $lastNameToken = collect($tokens)->firstWhere('token', '{{ order_last_name }}');
        $this->assertNotNull($lastNameToken);
        $this->assertEquals('The last name of the person who placed the order', $lastNameToken['description']);
    }

    public function test_can_get_attendee_ticket_tokens(): void
    {
        $response = $this->getJson('/email-templates/tokens/attendee_ticket', [
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

        $attendeeNameToken = collect($tokens)->firstWhere('token', '{{ attendee_name }}');
        $this->assertNotNull($attendeeNameToken);
    }

    public function test_invalid_template_type_returns_validation_error(): void
    {
        $response = $this->getJson('/email-templates/tokens/invalid_type', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_BAD_REQUEST);
    }

    public function test_unauthenticated_user_cannot_access_tokens(): void
    {
        $response = $this->getJson('/email-templates/tokens/order_confirmation');

        $response->assertStatus(ResponseCodes::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function test_tokens_include_order_specific_tokens(): void
    {
        $response = $this->getJson('/email-templates/tokens/order_confirmation', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_OK);
        
        $tokens = $response->json('tokens');
        $tokenNames = collect($tokens)->pluck('token')->toArray();
        
        $this->assertContains('{{ event_title }}', $tokenNames);
        $this->assertContains('{{ order_number }}', $tokenNames);
        $this->assertContains('{{ order_total }}', $tokenNames);
        $this->assertContains('{{ organizer_name }}', $tokenNames);
    }

    public function test_tokens_have_proper_structure(): void
    {
        $response = $this->getJson('/email-templates/tokens/order_confirmation', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        $response->assertStatus(ResponseCodes::HTTP_OK);
        
        $tokens = $response->json('tokens');
        
        foreach ($tokens as $token) {
            $this->assertArrayHasKey('token', $token);
            $this->assertArrayHasKey('description', $token);
            $this->assertArrayHasKey('example', $token);
            
            $this->assertNotEmpty($token['description']);
            // Most tokens should start with {{ and end with }}
            if (str_starts_with($token['token'], '{{')) {
                $this->assertStringEndsWith('}}', $token['token']);
            }
        }
    }
}