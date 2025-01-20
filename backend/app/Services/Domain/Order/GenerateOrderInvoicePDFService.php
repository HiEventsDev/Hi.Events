<?php

namespace HiEvents\Services\Domain\Order;

use Barryvdh\DomPDF\Facade\Pdf;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\DTO\InvoicePdfResponseDTO;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GenerateOrderInvoicePDFService
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    )
    {
    }

    public function generatePdfFromOrderShortId(string $orderShortId, int $eventId): InvoicePdfResponseDTO
    {
        return $this->generatePdf([
            'short_id' => $orderShortId,
            'event_id' => $eventId,
        ]);
    }

    public function generatePdfFromOrderId(int $orderId, int $eventId): InvoicePdfResponseDTO
    {
        return $this->generatePdf([
            'id' => $orderId,
            'event_id' => $eventId,
        ]);
    }

    private function generatePdf(array $whereCriteria): InvoicePdfResponseDTO
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(EventDomainObject::class, nested: [
                new Relationship(OrganizerDomainObject::class, name: 'organizer'),
                new Relationship(EventSettingDomainObject::class, name: 'event_settings'),
            ], name: 'event'))
            ->findFirstWhere($whereCriteria);

        if (!$order) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        $invoice = $this->invoiceRepository->findLatestInvoiceForOrder($order->getId());

        if (!$invoice) {
            throw new ResourceNotFoundException(__('Invoice not found'));
        }

        return new InvoicePdfResponseDTO(
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
