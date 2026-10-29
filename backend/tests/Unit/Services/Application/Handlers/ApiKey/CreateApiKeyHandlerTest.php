<?php

namespace Tests\Unit\Services\Application\Handlers\ApiKey;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\PersonalAccessTokenDomainObject;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\IdentityConfirmationFailedException;
use HiEvents\Exceptions\PermissionRequiredException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\ApiKey\CreateApiKeyHandler;
use HiEvents\Services\Application\Handlers\ApiKey\DTO\CreateApiKeyDTO;
use HiEvents\Services\Domain\Auth\ApiKey\ApiKeyService;
use HiEvents\Services\Domain\Auth\ApiKey\DTO\CreatedApiKeyDTO;
use HiEvents\Services\Domain\Auth\IdentityConfirmationService;
use Mockery as m;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateApiKeyHandlerTest extends TestCase
{
    private const USER_ID = 7;

    private const ACCOUNT_ID = 3;

    private UserRepositoryInterface|MockInterface $userRepository;

    private IdentityConfirmationService|MockInterface $identityConfirmationService;

    private ApiKeyService|MockInterface $apiKeyService;

    private CreateApiKeyHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = m::mock(UserRepositoryInterface::class);
        $this->identityConfirmationService = m::mock(IdentityConfirmationService::class);
        $this->apiKeyService = m::mock(ApiKeyService::class);

        $this->handler = new CreateApiKeyHandler($this->userRepository, $this->identityConfirmationService, $this->apiKeyService);
    }

    public function test_creates_the_key_after_confirming_identity(): void
    {
        $user = $this->user();
        $created = new CreatedApiKeyDTO(apiKey: new PersonalAccessTokenDomainObject, plainTextKey: 'hie_key');

        $this->apiKeyService->shouldReceive('countActiveKeys')->with(self::USER_ID, self::ACCOUNT_ID)->andReturn(0);
        $this->identityConfirmationService->shouldReceive('confirm')->once()->with($user, 'secret', null, 'jti-1');
        $this->apiKeyService->shouldReceive('create')->once()->with($user, self::ACCOUNT_ID, 'Zapier', ApiKeyAccess::READ_WRITE, 30)->andReturn($created);

        $this->assertSame($created, $this->handler->handle($this->dto()));
    }

    public function test_unverified_users_are_refused_before_anything_else(): void
    {
        $this->user(verified: false);

        $this->identityConfirmationService->shouldNotReceive('confirm');
        $this->apiKeyService->shouldNotReceive('create');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($this->dto());
    }

    public function test_members_without_edit_cannot_create_write_keys(): void
    {
        $this->user();

        $this->apiKeyService->shouldNotReceive('create');

        $this->expectException(PermissionRequiredException::class);

        $this->handler->handle($this->dto(canEdit: false));
    }

    public function test_members_without_edit_can_create_read_only_keys(): void
    {
        $user = $this->user();

        $this->apiKeyService->shouldReceive('countActiveKeys')->andReturn(0);
        $this->identityConfirmationService->shouldReceive('confirm')->once();
        $this->apiKeyService->shouldReceive('create')->once()->with($user, self::ACCOUNT_ID, 'Zapier', ApiKeyAccess::READ_ONLY, 30)
            ->andReturn(new CreatedApiKeyDTO(apiKey: new PersonalAccessTokenDomainObject, plainTextKey: 'hie_key'));

        $this->handler->handle($this->dto(access: ApiKeyAccess::READ_ONLY, canEdit: false));
    }

    public function test_the_active_key_limit_is_enforced(): void
    {
        $this->user();

        $this->apiKeyService->shouldReceive('countActiveKeys')->andReturn(ApiKeyService::MAX_ACTIVE_KEYS_PER_MEMBER);
        $this->apiKeyService->shouldNotReceive('create');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($this->dto());
    }

    public function test_a_failed_identity_check_creates_nothing(): void
    {
        $this->user();

        $this->apiKeyService->shouldReceive('countActiveKeys')->andReturn(0);
        $this->identityConfirmationService->shouldReceive('confirm')->andThrow(new IdentityConfirmationFailedException('The password is incorrect.'));
        $this->apiKeyService->shouldNotReceive('create');

        $this->expectException(IdentityConfirmationFailedException::class);

        $this->handler->handle($this->dto());
    }

    private function user(bool $verified = true): UserDomainObject
    {
        $user = (new UserDomainObject)
            ->setId(self::USER_ID)
            ->setEmail('owner@example.com')
            ->setEmailVerifiedAt($verified ? '2026-10-01 10:00:00' : null);

        $this->userRepository->shouldReceive('findById')->with(self::USER_ID)->andReturn($user);

        return $user;
    }

    private function dto(ApiKeyAccess $access = ApiKeyAccess::READ_WRITE, bool $canEdit = true): CreateApiKeyDTO
    {
        return new CreateApiKeyDTO(
            userId: self::USER_ID,
            accountId: self::ACCOUNT_ID,
            name: 'Zapier',
            access: $access,
            expiresInDays: 30,
            canEdit: $canEdit,
            password: 'secret',
            tokenId: 'jti-1',
        );
    }
}
