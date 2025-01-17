<?php

namespace HiEvents\Services\Application\Handlers\Order;

use Barryvdh\DomPDF\Facade\Pdf;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderInvoiceDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderInvoiceResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetOrderInvoiceHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    )
    {
    }

    public function handle(GetOrderInvoiceDTO $command): GetOrderInvoiceResponse
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(EventDomainObject::class, nested: [
                new Relationship(OrganizerDomainObject::class, name: 'organizer'),
                new Relationship(EventSettingDomainObject::class, name: 'event_settings'),
            ], name: 'event'))
            ->findFirstWhere([
                'id' => $command->orderId,
                'event_id' => $command->eventId,
            ]);

        if (!$order) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        $invoice = $this->invoiceRepository->findLatestInvoiceForOrder($order->getId());

        if (!$invoice) {
            throw new ResourceNotFoundException(__('Invoice not found'));
        }

        return new GetOrderInvoiceResponse(
            pdf: Pdf::loadView('invoice', [
                'order' => $order,
                'event' => $order->getEvent(),
                'organizer' => $order->getEvent()->getOrganizer(),
                'eventSettings' => $order->getEvent()->getEventSettings(),
                'invoice' => $invoice,
            ]),
            filename: $invoice->getInvoiceNumber() . '.pdf'
        );
    }
}
