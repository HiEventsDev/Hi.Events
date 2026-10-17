<?php

namespace Tests\Unit\Services\Domain\Auth;

use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\AccountUserRepositoryInterface;
use HiEvents\Services\Domain\Auth\AuthUserService;
use Illuminate\Auth\AuthManager;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery as m;
use Mockery\MockInterface;
use PHPOpenSourceSaver\JWTAuth\Payload;
use Tests\TestCase;

class AuthUserServiceTest extends TestCase
{
    private AuthManager|MockInterface $authManager;

    private AuthUserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authManager = m::mock(AuthManager::class);
        $this->authManager->shouldReceive('check')->andReturnTrue();

        $this->service = new AuthUserService($this->authManager, m::mock(AccountUserRepositoryInterface::class));
    }

    public function test_api_key_requests_read_their_context_from_the_key(): void
    {
        $this->actingWithApiKey(id: 42, accountId: 9, abilities: ['read']);
        $this->authManager->shouldNotReceive('payload');

        $this->assertTrue($this->service->isApiKeyRequest());
        $this->assertSame(9, $this->service->getAuthenticatedAccountId());
        $this->assertSame('api-key:42', $this->service->getAuthenticatedTokenKey());
        $this->assertFalse($this->service->apiKeyCanWrite());
        $this->assertFalse($this->service->isImpersonating());
        $this->assertNull($this->service->getImpersonatorId());
        $this->assertNull($this->service->getSessionTokenId());
    }

    public function test_write_keys_can_write(): void
    {
        $this->actingWithApiKey(id: 1, accountId: 9, abilities: ['read', 'write']);

        $this->assertTrue($this->service->apiKeyCanWrite());
    }

    public function test_session_requests_read_their_context_from_the_jwt(): void
    {
        $this->authManager->shouldReceive('user')->andReturn(new User);

        $payload = m::mock(Payload::class);
        $payload->shouldReceive('get')->with('account_id')->andReturn(5);
        $payload->shouldReceive('get')->with('is_impersonating')->andReturn(true);
        $payload->shouldReceive('get')->with('impersonator_id')->andReturn(11);
        $payload->shouldReceive('get')->with('jti')->andReturn('jti-abc');
        $this->authManager->shouldReceive('payload')->andReturn($payload);

        $this->assertFalse($this->service->isApiKeyRequest());
        $this->assertTrue($this->service->apiKeyCanWrite());
        $this->assertSame(5, $this->service->getAuthenticatedAccountId());
        $this->assertTrue($this->service->isImpersonating());
        $this->assertSame(11, $this->service->getImpersonatorId());
        $this->assertSame('jti-abc', $this->service->getSessionTokenId());
    }

    public function test_logout_only_ends_sessions(): void
    {
        $this->actingWithApiKey(id: 1, accountId: 9, abilities: ['read']);
        $this->authManager->shouldNotReceive('logout');

        $this->service->logout();

        $this->addToAssertionCount(1);
    }

    /**
     * @param  string[]  $abilities
     */
    private function actingWithApiKey(int $id, int $accountId, array $abilities): void
    {
        $apiKey = new PersonalAccessToken;
        $apiKey->forceFill(['id' => $id, 'account_id' => $accountId, 'abilities' => $abilities]);

        $this->authManager->shouldReceive('user')->andReturn((new User)->withAccessToken($apiKey));
    }
}
