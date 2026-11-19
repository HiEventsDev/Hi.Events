<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use Generator;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Exports\ContactsExport;
use HiEvents\Services\Infrastructure\Export\SpreadsheetFormulaEscaper;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ContactsExportTest extends TestCase
{
    public function test_the_file_starts_with_a_byte_order_mark_and_the_headings(): void
    {
        $csv = $this->write([]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(
            [['Email', 'First name', 'Last name', 'Email marketing', 'Events', 'Orders', 'Tickets', 'Check-ins', 'First seen', 'Last order', 'Last check-in', 'Tags', 'Mobile number', 'Text message marketing']],
            $this->rows($csv),
        );
    }

    public function test_every_page_is_written_in_order_as_one_row_per_contact(): void
    {
        $csv = $this->write([
            collect([
                $this->contact('ada@example.com'),
                $this->contact('bob@example.com')->setPhone('+353851234567')->setSmsMarketingStatus('SUBSCRIBED'),
            ]),
            collect([$this->contact('cy@example.com')]),
        ]);

        $rows = $this->rows($csv);
        $this->assertSame(['ada@example.com', 'bob@example.com', 'cy@example.com'], array_column(array_slice($rows, 1), 0));
        $this->assertSame(
            ['ada@example.com', 'Zoë "Zed"', 'O\'Néill, Jr', 'SUBSCRIBED', '2', '3', '4', '1', '2026-01-01 10:00:00', '2026-02-01 10:00:00', '', 'VIP, Press', '', 'NOT_SUBSCRIBED'],
            $rows[1],
        );
        $this->assertSame(['+353851234567', 'SUBSCRIBED'], array_slice($rows[2], 12));
        $this->assertSame(1, substr_count($csv, "\xEF\xBB\xBF"));
    }

    public function test_pages_are_pulled_lazily_while_writing(): void
    {
        $pulled = 0;
        $pages = (function () use (&$pulled): Generator {
            foreach (['ada@example.com', 'bob@example.com'] as $email) {
                $pulled++;

                yield collect([$this->contact($email)]);
            }
        })();

        $this->assertSame(0, $pulled);
        $csv = $this->write($pages);

        $this->assertSame(2, $pulled);
        $this->assertCount(3, $this->rows($csv));
    }

    public function test_cells_a_spreadsheet_would_run_are_escaped(): void
    {
        $escaper = new SpreadsheetFormulaEscaper;
        $contact = $this->contact('formula@example.com')
            ->setFirstName('=HYPERLINK("http://evil.test")')
            ->setLastName('+1+1')
            ->setTags(collect([(object) ['name' => '@SUM(A1)']]));

        $row = $this->rows($this->write([collect([$contact])]))[1];

        $this->assertSame(
            [$escaper->escape('=HYPERLINK("http://evil.test")'), $escaper->escape('+1+1'), $escaper->escape('@SUM(A1)')],
            [$row[1], $row[2], $row[11]],
        );
        foreach ($row as $cell) {
            $this->assertFalse($escaper->isFormulaTrigger($cell), $cell);
        }
    }

    /**
     * @param  iterable<Collection<int, CrmContactDomainObject>>  $pages
     */
    private function write(iterable $pages): string
    {
        $handle = fopen('php://memory', 'w+');
        (new ContactsExport(new SpreadsheetFormulaEscaper))->write($handle, $pages);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rows(string $csv): array
    {
        $lines = array_values(array_filter(explode("\n", substr($csv, 3)), static fn (string $line) => $line !== ''));

        return array_map(static fn (string $line) => str_getcsv($line, ',', '"', ''), $lines);
    }

    private function contact(string $email): CrmContactDomainObject
    {
        return (new CrmContactDomainObject)
            ->setEmail($email)
            ->setFirstName('Zoë "Zed"')
            ->setLastName('O\'Néill, Jr')
            ->setEmailMarketingStatus('SUBSCRIBED')
            ->setEventsCount(2)
            ->setOrdersCount(3)
            ->setTicketsCount(4)
            ->setAttendedCount(1)
            ->setFirstSeenAt('2026-01-01 10:00:00')
            ->setLastOrderAt('2026-02-01 10:00:00')
            ->setLastAttendedAt(null)
            ->setTags(collect([(object) ['name' => 'VIP'], (object) ['name' => 'Press']]));
    }
}
