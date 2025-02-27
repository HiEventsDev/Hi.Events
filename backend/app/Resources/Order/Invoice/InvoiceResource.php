<?php

namespace HiEvents\Resources\Order\Invoice;

use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\Resources\BaseResource;

/** @mixin InvoiceDomainObject */
class InvoiceResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'invoice_number' => $this->getInvoiceNumber(),
            'order_id' => $this->getOrderId(),
            'status' => $this->getStatus(),
        ];
    }
}
