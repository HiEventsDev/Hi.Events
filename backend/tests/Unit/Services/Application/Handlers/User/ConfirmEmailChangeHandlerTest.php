<?php

namespace Tests\Unit\Services\Application\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Application\Handlers\User\ConfirmEmailChangeHandler;
use HiEvents\Services\Application\Handlers\User\DTO\ConfirmEmailChangeDTO;
use HiEvents\Services\Infrastructure\Encryption\EncryptedPayloadService;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Psr\Log\NullLogger;
use Tests\TestCase;

class ConfirmEmailChangeHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private UserRepositoryInterface|MockInterface $userRepository;

    private EncryptedPayloadService|MockInterface $encryptedPayloadService;

    private ConfirmEmailChangeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->encryptedPayloadService = Mockery::mock(EncryptedPayloadService::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());

        $this->userRepository->shouldReceive('findByIdAndAccountId')->with(1, 9)->andReturn(
            (new UserDomainObject)->setId(1)->setEmail('old@example.com')->setPendingEmail('new@example.com')
        );

        $this->handler = new ConfirmEmailChangeHandler(
            new NullLogger,
            $this->userRepository,
            $this->encryptedPayloadService,
            $databaseManager,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_confirms_the_pending_email_named_in_the_token(): void
    {
        $this->givenTokenPayload(['id' => 1, 'email' => 'new@example.com']);
        $this->userRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->userRepository->shouldReceive('updateWhere')->once()->with(
            ['email' => 'new@example.com', 'pending_email' => null],
            ['id' => 1],
        );

        $this->handler->handle(new ConfirmEmailChangeDTO(token: 'token', accountId: 9));
    }

    public function test_rejects_a_token_issued_for_a_different_pending_email(): void
    {
        $this->givenTokenPayload(['id' => 1, 'email' => 'earlier@example.com']);
        $this->userRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle(new ConfirmEmailChangeDTO(token: 'token', accountId: 9));
    }

    public function test_rejects_tokens_without_an_email(): void
    {
        $this->givenTokenPayload(['id' => 1]);
        $this->userRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle(new ConfirmEmailChangeDTO(token: 'token', accountId: 9));
    }

    public function test_rejects_an_email_another_user_took_in_the_meantime(): void
    {
        $this->givenTokenPayload(['id' => 1, 'email' => 'new@example.com']);
        $this->userRepository->shouldReceive('findFirstWhere')->andReturn((new UserDomainObject)->setId(2));
        $this->userRepository->shouldNotReceive('updateWhere');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle(new ConfirmEmailChangeDTO(token: 'token', accountId: 9));
    }

    private function givenTokenPayload(array $payload): void
    {
        $this->encryptedPayloadService->shouldReceive('decryptPayload')->with('token')->andReturn($payload);
    }
}
