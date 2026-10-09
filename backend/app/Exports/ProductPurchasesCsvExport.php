<?php

namespace HiEvents\Exports;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\ProductPurchaseStatus;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseDTO;
use HiEvents\Services\Application\Handlers\Product\Purchases\DTO\ProductPurchaseExportDTO;
use HiEvents\Services\Infrastructure\Export\SpreadsheetFormulaEscaper;

class ProductPurchasesCsvExport
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    public function __construct(
        private readonly SpreadsheetFormulaEscaper $formulaEscaper,
    ) {}

    /**
     * @param  resource  $handle
     */
    public function write($handle, ProductPurchaseExportDTO $export): void
    {
        fwrite($handle, self::UTF8_BOM);
        fputcsv($handle, $this->headings(), escape: '');

        foreach ($export->purchases as $purchase) {
            fputcsv($handle, $this->formulaEscaper->escapeRow($this->row($purchase, $export->timezone)), escape: '');
        }
    }

    private function headings(): array
    {
        return [
            __('Order'),
            __('Order Date'),
            __('First Name'),
            __('Last Name'),
            __('Email'),
            __('Product'),
            __('Price Tier'),
            __('Occurrence Date'),
            __('Status'),
            __('Quantity Sold'),
            __('Quantity Awaiting Payment'),
            __('Quantity Cancelled'),
            __('Line Total'),
            __('Currency'),
            __('Order Status'),
            __('Refund Status'),
        ];
    }

    private function row(ProductPurchaseDTO $purchase, string $timezone): array
    {
        return [
            $purchase->orderPublicId,
            $this->formatDate($purchase->orderCreatedAt, $timezone),
            $purchase->firstName,
            $purchase->lastName,
            $purchase->email,
            $purchase->productTitle,
            $purchase->priceLabel,
            $purchase->occurrenceStartDate ? $this->formatDate($purchase->occurrenceStartDate, $timezone) : '',
            $this->getStatusLabel($purchase->status),
            $purchase->soldQuantity,
            $purchase->awaitingPaymentQuantity,
            $purchase->cancelledQuantity,
            $purchase->lineTotal !== null ? number_format($purchase->lineTotal, 2, '.', '') : '',
            $purchase->currency,
            OrderStatus::getHumanReadableStatus($purchase->orderStatus),
            $this->getRefundStatusLabel($purchase->refundStatus),
        ];
    }

    private function formatDate(string $date, string $timezone): string
    {
        return Carbon::parse($date, 'UTC')->setTimezone($timezone)->format('Y-m-d H:i');
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            ProductPurchaseStatus::SOLD->name => __('Sold'),
            ProductPurchaseStatus::AWAITING_PAYMENT->name => __('Awaiting payment'),
            ProductPurchaseStatus::CANCELLED->name => __('Cancelled'),
        };
    }

    private function getRefundStatusLabel(?string $refundStatus): string
    {
        return match ($refundStatus) {
            OrderRefundStatus::REFUNDED->name => __('Refunded'),
            OrderRefundStatus::PARTIALLY_REFUNDED->name => __('Partially refunded'),
            OrderRefundStatus::REFUND_PENDING->name => __('Refund pending'),
            OrderRefundStatus::REFUND_FAILED->name => __('Refund failed'),
            default => '',
        };
    }
}
