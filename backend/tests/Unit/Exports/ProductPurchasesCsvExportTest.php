<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use HiEvents\Exports\ProductPurchasesCsvExport;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseDTO;
use HiEvents\Services\Application\Handlers\Product\Purchases\DTO\ProductPurchaseExportDTO;
use HiEvents\Services\Infrastructure\Export\SpreadsheetFormulaEscaper;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

class ProductPurchasesCsvExportTest extends TestCase
{
    public function test_writes_rows_in_the_event_timezone_with_formulas_escaped(): void
    {
        $handle = fopen('php://memory', 'w+');

        (new ProductPurchasesCsvExport(new SpreadsheetFormulaEscaper))->write($handle, new ProductPurchaseExportDTO(
            purchases: LazyCollection::make([
                $this->purchase(firstName: '=HYPERLINK("https://evil.test")', refundStatus: 'PARTIALLY_REFUNDED'),
                $this->purchase(status: 'CANCELLED', soldQuantity: 0, cancelledQuantity: 2, lineTotal: null),
            ]),
            timezone: 'America/New_York',
        ));

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);

        $rows = array_map(str_getcsv(...), explode("\n", trim(substr($contents, 3))));
        $this->assertCount(3, $rows);
        $this->assertSame('Order', $rows[0][0]);

        $this->assertSame('O-ABC', $rows[1][0]);
        $this->assertSame('2026-10-01 06:00', $rows[1][1]);
        $this->assertSame('\'=HYPERLINK("https://evil.test")', $rows[1][2]);
        $this->assertSame('2026-11-01 15:00', $rows[1][7]);
        $this->assertSame('Sold', $rows[1][8]);
        $this->assertSame('2', $rows[1][9]);
        $this->assertSame('20.50', $rows[1][12]);
        $this->assertSame('Completed', $rows[1][14]);
        $this->assertSame('Partially refunded', $rows[1][15]);

        $this->assertSame('Cancelled', $rows[2][8]);
        $this->assertSame('2', $rows[2][11]);
        $this->assertSame('', $rows[2][12]);
        $this->assertSame('', $rows[2][15]);
    }

    private function purchase(
        string $firstName = 'Ada',
        ?string $refundStatus = null,
        string $status = 'SOLD',
        int $soldQuantity = 2,
        int $cancelledQuantity = 0,
        ?float $lineTotal = 20.5,
    ): ProductPurchaseDTO {
        return new ProductPurchaseDTO(
            orderId: 1,
            orderPublicId: 'O-ABC',
            orderStatus: 'COMPLETED',
            refundStatus: $refundStatus,
            orderCreatedAt: '2026-10-01 10:00:00',
            currency: 'USD',
            firstName: $firstName,
            lastName: 'Lovelace',
            email: 'ada@example.test',
            productTitle: 'General Admission',
            productPriceId: 1,
            priceLabel: null,
            eventOccurrenceId: 1,
            occurrenceStartDate: '2026-11-01 20:00:00',
            soldQuantity: $soldQuantity,
            awaitingPaymentQuantity: 0,
            cancelledQuantity: $cancelledQuantity,
            lineTotal: $lineTotal,
            status: $status,
        );
    }
}
