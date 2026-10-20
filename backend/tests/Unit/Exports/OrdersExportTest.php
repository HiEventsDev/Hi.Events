<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exports\OrdersExport;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrdersExportTest extends TestCase
{
    public function test_door_sales_carry_the_cash_reconciliation_columns_beside_the_tender(): void
    {
        $order = (new OrderDomainObject)
            ->setId(1)
            ->setPublicId('O-1')
            ->setShortId('o_1')
            ->setStatus(OrderStatus::COMPLETED->name)
            ->setCurrency('USD')
            ->setTotalGross(20.0)
            ->setBoxOfficeId(4)
            ->setBoxOfficeTender(BoxOfficeTender::CASH->value)
            ->setBoxOfficeOperatorName('Sam')
            ->setBoxOfficeAmountTendered(50.0)
            ->setBoxOfficeChangeDue(30.0)
            ->setBoxOfficeReference('Till 2')
            ->setCreatedAt('2026-09-20 10:00:00')
            ->setOrderItems(new Collection)
            ->setQuestionAndAnswerViews(new Collection);

        $export = (new OrdersExport(new QuestionAnswerFormatter))
            ->withData(new LengthAwarePaginator([$order], 1, 10), new Collection);

        $tenderColumn = array_search(__('Tender'), $export->headings(), true);

        $this->assertSame(
            [__('Tender'), __('Operator'), __('Amount Tendered'), __('Change Due'), __('Reference')],
            array_slice($export->headings(), $tenderColumn, 5),
        );
        $this->assertSame(
            [BoxOfficeTender::CASH->value, 'Sam', 50.0, 30.0, 'Till 2'],
            array_slice($export->map($order), $tenderColumn, 5),
        );
        $this->assertCount(count($export->headings()), $export->map($order));
    }

    public function test_door_sale_columns_come_after_every_existing_column_so_spreadsheets_do_not_shift(): void
    {
        $question = (new QuestionDomainObject)->setId(9)->setTitle('Dietary needs')->setType(QuestionTypeEnum::SINGLE_LINE_TEXT->name);
        $export = (new OrdersExport(new QuestionAnswerFormatter))
            ->withData(new LengthAwarePaginator([], 0, 10), new Collection([$question]));

        $headings = $export->headings();

        $this->assertSame(__('Opted In To Marketing'), $headings[24]);
        $this->assertSame('Dietary needs', $headings[25]);
        $this->assertSame(
            [__('Sales Channel'), __('Tender'), __('Operator'), __('Amount Tendered'), __('Change Due'), __('Reference')],
            array_slice($headings, 26),
        );
    }
}
