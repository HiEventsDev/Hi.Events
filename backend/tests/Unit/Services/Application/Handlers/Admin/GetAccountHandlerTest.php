<?php

namespace Tests\Unit\Services\Application\Handlers\Admin;

use HiEvents\Models\Account;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Services\Application\Handlers\Admin\GetAccountHandler;
use Mockery;
use Tests\TestCase;

class GetAccountHandlerTest extends TestCase
{
    private AccountRepositoryInterface $repository;
    private GetAccountHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(AccountRepositoryInterface::class);
        $this->handler = new GetAccountHandler($this->repository);
    }

    public function testHandleReturnsAccountWithDetails(): void
    {
        $accountId = 123;
        $account = Mockery::mock(Account::class);

        $this->repository
            ->shouldReceive('getAccountWithDetails')
            ->with($accountId)
            ->once()
            ->andReturn($account);

        $result = $this->handler->handle($accountId);

        $this->assertSame($account, $result);
    }

    public function testHandleThrowsExceptionWhenAccountNotFound(): void
    {
        $accountId = 999;

        $this->repository
            ->shouldReceive('getAccountWithDetails')
            ->with($accountId)
            ->once()
            ->andThrow(new \Illuminate\Database\Eloquent\ModelNotFoundException());

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->handler->handle($accountId);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
