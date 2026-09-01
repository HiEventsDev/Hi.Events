<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\UpdateAccountVerificationHandler;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateAccountVerificationHandlerTest extends TestCase
{
    private AccountRepositoryInterface|MockInterface $accountRepository;

    private UpdateAccountVerificationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
        $this->handler = new UpdateAccountVerificationHandler($this->accountRepository);
    }

    public function test_it_marks_the_account_as_manually_verified(): void
    {
        $account = (new AccountDomainObject)->setId(42)->setIsManuallyVerified(true);

        $this->accountRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(42, ['is_manually_verified' => true])
            ->andReturn($account);

        $result = $this->handler->handle(42, true);

        $this->assertTrue($result->getIsManuallyVerified());
    }

    public function test_it_revokes_manual_verification(): void
    {
        $account = (new AccountDomainObject)->setId(7)->setIsManuallyVerified(false);

        $this->accountRepository
            ->shouldReceive('updateFromArray')
            ->once()
            ->with(7, ['is_manually_verified' => false])
            ->andReturn($account);

        $result = $this->handler->handle(7, false);

        $this->assertFalse($result->getIsManuallyVerified());
    }
}
