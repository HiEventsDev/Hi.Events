<?php

namespace HiEvents\Exports;

use Carbon\Carbon;
use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\CashlessTransactionItemDomainObject;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CashlessTransactionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private LengthAwarePaginator $transactions;

    public function withData(LengthAwarePaginator $transactions): CashlessTransactionsExport
    {
        $this->transactions = $transactions;

        return $this;
    }

    public function collection(): Collection
    {
        return collect($this->transactions->items());
    }

    public function headings(): array
    {
        return [
            __('Reference'),
            __('Date'),
            __('Type'),
            __('Amount'),
            __('Balance After'),
            __('Ticket ID'),
            __('Attendee'),
            __('Sales Point'),
            __('Payment Method'),
            __('Items'),
            __('Notes'),
        ];
    }

    /**
     * @param  CashlessTransactionDomainObject  $transaction
     */
    public function map($transaction): array
    {
        $attendee = $transaction->getWallet()?->getAttendee();

        return [
            $transaction->getShortId(),
            Carbon::parse($transaction->getCreatedAt())->format('Y-m-d H:i:s'),
            CashlessTransactionType::getHumanReadableType($transaction->getType()),
            $transaction->getAmount(),
            $transaction->getBalanceAfter(),
            $attendee?->getPublicId(),
            $attendee ? trim($attendee->getFirstName().' '.$attendee->getLastName()) : null,
            $transaction->getSalesPoint()?->getName(),
            $transaction->getStaffPaymentMethod(),
            $this->describeItems($transaction),
            $transaction->getNotes(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function describeItems(CashlessTransactionDomainObject $transaction): ?string
    {
        $items = $transaction->getItems();

        if ($items === null || $items->isEmpty()) {
            return null;
        }

        return $items
            ->map(fn (CashlessTransactionItemDomainObject $item) => sprintf(
                '%d x %s',
                $item->getQuantity(),
                $item->getProductTitle(),
            ))
            ->implode(', ');
    }
}
