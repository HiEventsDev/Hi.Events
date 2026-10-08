<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Exports\AttendeesExport;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AttendeesExportTest extends TestCase
{
    private function export(bool $includeSeats): AttendeesExport
    {
        return (new AttendeesExport(new QuestionAnswerFormatter))
            ->withData(new Collection([$this->attendee()]), new Collection, new Collection, $includeSeats);
    }

    private function attendee(): AttendeeDomainObject
    {
        return (new AttendeeDomainObject)
            ->setId(1)
            ->setFirstName('Ada')
            ->setLastName('Seated')
            ->setEmail('ada@example.com')
            ->setStatus(AttendeeStatus::ACTIVE->name)
            ->setEventId(7)
            ->setProductId(3)
            ->setProductPriceId(4)
            ->setPublicId('pub_1')
            ->setShortId('a_1')
            ->setSeatLabel('Stalls · A-10')
            ->setCreatedAt('2026-09-20 10:00:00')
            ->setUpdatedAt('2026-09-20 10:00:00')
            ->setProduct((new ProductDomainObject)->setId(3)->setTitle('Premium Seat'));
    }

    public function test_an_event_without_a_seat_map_keeps_the_original_columns(): void
    {
        $export = $this->export(includeSeats: false);

        $this->assertNotContains(__('Seat'), $export->headings());
        $this->assertSame(
            ['ID', 'First Name', 'Last Name', 'Email', 'Status', 'Check Ins', 'Product ID', 'Product Name', 'Event ID', 'Occurrence Date', 'Public ID'],
            array_slice($export->headings(), 0, 11),
        );
        $this->assertSame('pub_1', $export->map($this->attendee())[10]);
    }

    public function test_a_seated_event_gains_a_seat_column_beside_the_occurrence_date(): void
    {
        $export = $this->export(includeSeats: true);

        $this->assertSame(__('Seat'), $export->headings()[10]);
        $this->assertSame('Stalls · A-10', $export->map($this->attendee())[10]);
        $this->assertSame('pub_1', $export->map($this->attendee())[11]);
    }

    public function test_the_two_shapes_differ_by_exactly_one_column(): void
    {
        $this->assertCount(count($this->export(includeSeats: false)->headings()) + 1, $this->export(includeSeats: true)->headings());
    }

    public function test_the_sales_channel_column_comes_last_so_existing_columns_do_not_shift(): void
    {
        $export = $this->export(includeSeats: false);

        $this->assertSame(__('Notes'), $export->headings()[14]);
        $this->assertSame(__('Sales Channel'), $export->headings()[15]);
        $this->assertSame(__('Online'), $export->map($this->attendee())[15]);
    }
}
