<?php

namespace Tests\Unit\Exports;

use HiEvents\Exports\AnswersExport;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Maatwebsite\Excel\Concerns\Export;
use Mockery as m;
use Tests\TestCase;

class AnswersExportTest extends TestCase
{
    public function test_export_and_sheets_satisfy_the_excel_export_contract(): void
    {
        $export = new AnswersExport(m::mock(QuestionAnswerFormatter::class));

        $this->assertInstanceOf(Export::class, $export);

        $sheets = $export->withData(collect())->sheets();

        $this->assertCount(3, $sheets);
        $this->assertContainsOnlyInstancesOf(Export::class, $sheets);
    }
}
