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
            'account_id' => $this->getAccountId(),
            'status' => $this->getStatus(),
            'total_amount' => $this->getTotalAmount(),
            'issue_date' => $this->getIssueDate(),
            'due_date' => $this->getDueDate(),
            'items' => $this->getItems(),
            'taxes_and_fees' => $this->getTaxesAndFees(),
            'created_at' => $this->getCreatedAt(),
            'order' => $this->getOrder() ? [
                'id' => $this->getOrder()->getId(),
                'short_id' => $this->getOrder()->getShortId(),
                'first_name' => $this->getOrder()->getFirstName(),
                'last_name' => $this->getOrder()->getLastName(),
                'email' => $this->getOrder()->getEmail(),
                'status' => $this->getOrder()->getStatus(),
            ] : null,
        ];
    }
}
