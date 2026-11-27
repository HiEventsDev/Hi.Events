<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Exports;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Services\Infrastructure\Export\SpreadsheetFormulaEscaper;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactsExport
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    public function __construct(
        private readonly SpreadsheetFormulaEscaper $formulaEscaper,
    ) {}

    /**
     * @param  iterable<Collection<int, CrmContactDomainObject>>  $pages
     */
    public function download(iterable $pages): StreamedResponse
    {
        return new StreamedResponse(function () use ($pages): void {
            $handle = fopen('php://output', 'w');
            $this->write($handle, $pages);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contacts.csv"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  iterable<Collection<int, CrmContactDomainObject>>  $pages
     */
    public function write($handle, iterable $pages): void
    {
        fwrite($handle, self::UTF8_BOM);
        fputcsv($handle, $this->headings(), escape: '');

        foreach ($pages as $contacts) {
            foreach ($contacts as $contact) {
                fputcsv($handle, $this->formulaEscaper->escapeRow($this->row($contact)), escape: '');
            }
        }
    }

    private function headings(): array
    {
        return [
            __('Email'),
            __('First name'),
            __('Last name'),
            __('Email marketing'),
            __('Events'),
            __('Orders'),
            __('Tickets'),
            __('Check-ins'),
            __('First seen'),
            __('Last order'),
            __('Last check-in'),
            __('Tags'),
            __('Mobile number'),
            __('Text message marketing'),
        ];
    }

    private function row(CrmContactDomainObject $contact): array
    {
        return [
            $contact->getEmail(),
            $contact->getFirstName(),
            $contact->getLastName(),
            $contact->getEmailMarketingStatus(),
            $contact->getEventsCount(),
            $contact->getOrdersCount(),
            $contact->getTicketsCount(),
            $contact->getAttendedCount(),
            $contact->getFirstSeenAt(),
            $contact->getLastOrderAt(),
            $contact->getLastAttendedAt(),
            ($contact->getTags() ?? collect())->pluck('name')->implode(', '),
            $contact->getPhone(),
            $contact->getSmsMarketingStatus(),
        ];
    }
}
