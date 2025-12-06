<?php

namespace Tests\Unit\Services\Application\Handlers\Account\Vat;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\Account\Vat\GetAccountVatSettingHandler;
use Mockery;
use Tests\TestCase;

class GetAccountVatSettingHandlerTest extends TestCase
{
    private AccountVatSettingRepositoryInterface $repository;
    private GetAccountVatSettingHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(AccountVatSettingRepositoryInterface::class);
        $this->handler = new GetAccountVatSettingHandler($this->repository);
    }

    public function testHandleReturnsVatSetting(): void
    {
        $accountId = 123;
        $vatSetting = Mockery::mock(AccountVatSettingDomainObject::class);

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn($vatSetting);

        $result = $this->handler->handle($accountId);

        $this->assertSame($vatSetting, $result);
    }

    public function testHandleReturnsNullWhenNotFound(): void
    {
        $accountId = 456;

        $this->repository
            ->shouldReceive('findByAccountId')
            ->with($accountId)
            ->once()
            ->andReturn(null);

        $result = $this->handler->handle($accountId);

        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
