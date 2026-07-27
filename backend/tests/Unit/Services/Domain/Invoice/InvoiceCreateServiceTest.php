<?php

namespace Tests\Unit\Services\Domain\Invoice;

use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Invoice\InvoiceCreateService;
use Mockery;
use Tests\TestCase;

class InvoiceCreateServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_existing_invoice_instead_of_throwing(): void
    {
        $existingInvoice = new InvoiceDomainObject;
        $existingInvoice->setId(7);

        $invoiceRepository = Mockery::mock(InvoiceRepositoryInterface::class);
        $invoiceRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with(['order_id' => 123])
            ->andReturn($existingInvoice);

        $invoiceRepository->shouldNotReceive('create');

        $orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $orderRepository->shouldNotReceive('findById');

        $service = new InvoiceCreateService($orderRepository, $invoiceRepository);

        $result = $service->createInvoiceForOrder(123);

        $this->assertSame(7, $result->getId());
    }
}
