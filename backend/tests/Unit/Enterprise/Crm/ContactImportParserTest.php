<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Exceptions\InvalidContactImportException;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportParser;
use HiEvents\Enterprise\Crm\Services\Domain\Import\DTO\ParsedContactImportDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactImportParserTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);

        parent::tearDown();
    }

    public static function emailHeaders(): array
    {
        return [
            'Email' => ['Email'],
            'upper case' => ['EMAIL'],
            'E-mail' => ['E-mail'],
            'Email Address' => ['Email Address'],
            'padded' => ['  email  '],
            'with a byte order mark' => ["\xEF\xBB\xBFEmail"],
            'quoted with a byte order mark' => ["\xEF\xBB\xBF\"Email\""],
        ];
    }

    #[DataProvider('emailHeaders')]
    public function test_the_email_column_is_found_whatever_it_is_called(string $header): void
    {
        $parsed = $this->parse("$header,Name\ngrace@example.com,Grace Hopper\n");

        $this->assertSame(['grace@example.com'], array_keys($parsed->rows));
        $this->assertSame(['email' => 'grace@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'reason' => CrmEmailMarketingStatus::UNSUBSCRIBED], $parsed->rows['grace@example.com']);
    }

    public static function nameColumns(): array
    {
        return [
            'first and last name' => ['Email,First Name,Last Name', 'ada@example.com,Ada,Lovelace', 'Ada', 'Lovelace'],
            'snake case headers' => ['email,first_name,last_name', 'ada@example.com,Ada,Lovelace', 'Ada', 'Lovelace'],
            'surname and forename' => ['email,surname,forename', 'ada@example.com,Lovelace,Ada', 'Ada', 'Lovelace'],
            'full name is split on the first space' => ['email,full name', 'ada@example.com,Ada King Lovelace', 'Ada', 'King Lovelace'],
            'single word full name' => ['email,name', 'ada@example.com,Ada', 'Ada', null],
            'first name wins over full name' => ['email,name,first name', 'ada@example.com,Augusta Byron,Ada', 'Ada', null],
            'no name columns' => ['email,phone', 'ada@example.com,123', null, null],
            'blank names' => ['email,first name,last name', 'ada@example.com, ,', null, null],
            'a link as a name is dropped' => ['email,first name,last name', 'ada@example.com,https://spam.example,Lovelace', null, 'Lovelace'],
            'a domain as a name is dropped' => ['email,first name,last name', 'ada@example.com,Ada,win-prizes.com', 'Ada', null],
            'a name over fifty characters is dropped' => ['email,first name,last name', 'ada@example.com,'.str_repeat('a', 51).',Lovelace', null, 'Lovelace'],
            'markup in a name is dropped' => ['email,name', 'ada@example.com,<b>Ada</b> Lovelace', null, 'Lovelace'],
        ];
    }

    #[DataProvider('nameColumns')]
    public function test_names_are_read_split_and_dropped_when_unsafe(string $header, string $row, ?string $firstName, ?string $lastName): void
    {
        $parsed = $this->parse("$header\n$row\n");

        $this->assertSame([$firstName, $lastName], [$parsed->rows['ada@example.com']['first_name'], $parsed->rows['ada@example.com']['last_name']]);
    }

    public function test_a_semicolon_delimited_file_with_a_byte_order_mark_and_windows_line_endings_is_read(): void
    {
        $parsed = $this->parse("\xEF\xBB\xBFE-mail;First Name;Last Name\r\nada@example.com;Ada;Lovelace\r\n\"grace@example.com\";\"Grace\";\"Hopper; Rear Admiral\"\r\n");

        $this->assertSame(2, $parsed->total_rows);
        $this->assertSame(['email' => 'ada@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'reason' => CrmEmailMarketingStatus::UNSUBSCRIBED], $parsed->rows['ada@example.com']);
        $this->assertSame('Hopper; Rear Admiral', $parsed->rows['grace@example.com']['last_name']);
    }

    public function test_invalid_and_duplicate_rows_are_counted_and_blank_lines_ignored(): void
    {
        $parsed = $this->parse(implode("\n", [
            'Email,Name',
            'Ada@Example.com,Ada Lovelace',
            '',
            ' ada@example.com ,Someone Else',
            'not-an-email,Bad Row',
            ',No Email',
            'grace@example.com',
            '',
        ]));

        $this->assertSame(5, $parsed->total_rows);
        $this->assertSame(2, $parsed->invalid_rows);
        $this->assertSame(['ada@example.com', 'grace@example.com'], array_keys($parsed->rows));
        $this->assertSame('Ada', $parsed->rows['ada@example.com']['first_name']);
        $this->assertNull($parsed->rows['grace@example.com']['first_name']);
    }

    public static function repeatedSuppressionReasons(): array
    {
        return [
            'complaint after an unsubscribe' => [['unsubscribed', 'complained'], CrmEmailMarketingStatus::COMPLAINED],
            'unsubscribe after a complaint' => [['complained', 'unsubscribed'], CrmEmailMarketingStatus::COMPLAINED],
            'bounce after an unsubscribe' => [['unsubscribed', 'bounced'], CrmEmailMarketingStatus::UNSUBSCRIBED],
            'unsubscribe after a bounce' => [['bounced', 'unsubscribed'], CrmEmailMarketingStatus::UNSUBSCRIBED],
            'complaint between a bounce and an unsubscribe' => [['bounced', 'spam', 'unsubscribed'], CrmEmailMarketingStatus::COMPLAINED],
            'the same reason twice' => [['bounced', 'bounced'], CrmEmailMarketingStatus::BOUNCED],
        ];
    }

    /**
     * @param  string[]  $reasons
     */
    #[DataProvider('repeatedSuppressionReasons')]
    public function test_an_address_listed_more_than_once_keeps_its_strongest_suppression_reason(array $reasons, CrmEmailMarketingStatus $expected): void
    {
        $parsed = $this->parse("Email,Reason\n".implode("\n", array_map(static fn (string $reason) => "ada@example.com,$reason", $reasons)));

        $this->assertSame(count($reasons), $parsed->total_rows);
        $this->assertSame(['ada@example.com'], array_keys($parsed->rows));
        $this->assertSame($expected, $parsed->rows['ada@example.com']['reason']);
    }

    public static function malformedAddresses(): array
    {
        return [
            'comma in the domain' => ['"john@gmail,com"'],
            'trailing semicolon' => ['"john@example.com;"'],
            'angle brackets' => ['<john@example.com>'],
            'consecutive dots' => ['john..doe@example.com'],
            'display name' => ['John <john@example.com>'],
            'longer than an address can be' => [str_repeat('a', 64).'@'.str_repeat('b', 186).'.com'],
        ];
    }

    #[DataProvider('malformedAddresses')]
    public function test_an_address_the_add_contact_form_would_refuse_is_counted_as_invalid(string $cell): void
    {
        $parsed = $this->parse("Email\n$cell\ngrace@example.com\n");

        $this->assertSame(2, $parsed->total_rows);
        $this->assertSame(1, $parsed->invalid_rows);
        $this->assertSame(['grace@example.com'], array_keys($parsed->rows));
    }

    public function test_exactly_the_maximum_number_of_rows_is_accepted_and_one_more_is_refused(): void
    {
        $rows = array_map(static fn (int $index) => "person$index@example.com", range(1, ContactImportParser::MAX_ROWS));

        $this->assertCount(ContactImportParser::MAX_ROWS, $this->parse("Email\n".implode("\n", $rows))->rows);

        $this->expectException(InvalidContactImportException::class);
        $this->parse("Email\n".implode("\n", $rows)."\nnot-an-email\n");
    }

    public static function unreadableFiles(): array
    {
        return [
            'empty file' => [''],
            'no email column' => ["Name,Phone\nGrace,123\n"],
            'data without a header' => ["grace@example.com,Grace\n"],
            'email in the name of another column' => ["Email of friend,Name\ngrace@example.com,Grace\n"],
        ];
    }

    #[DataProvider('unreadableFiles')]
    public function test_a_file_without_an_email_column_is_refused(string $contents): void
    {
        $this->expectException(InvalidContactImportException::class);

        $this->parse($contents);
    }

    public function test_a_windows_1252_file_is_read_as_utf8(): void
    {
        $parsed = $this->parse("E-mail;First Name;Last Name\r\njose@example.com;Jos\xE9;Mu\xF1oz\r\n");

        $this->assertSame(['jose@example.com' => ['email' => 'jose@example.com', 'first_name' => 'José', 'last_name' => 'Muñoz', 'reason' => CrmEmailMarketingStatus::UNSUBSCRIBED]], $parsed->rows);
    }

    public function test_a_file_without_a_valid_address_is_rejected(): void
    {
        foreach (["Email,Name\n", "Email\nnot-an-email\n"] as $contents) {
            try {
                $this->parse($contents);
                $this->fail('Expected the file to be rejected.');
            } catch (InvalidContactImportException $exception) {
                $this->assertSame('We could not find any valid email addresses in that file.', $exception->getMessage());
            }
        }
    }

    private function parse(string $contents): ParsedContactImportDTO
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-import-');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return (new ContactImportParser(new ContactEmailNormalizer))->parse($path);
    }

    public static function reasons(): array
    {
        return [
            'blank' => ['', CrmEmailMarketingStatus::UNSUBSCRIBED],
            'unsubscribed' => ['Unsubscribed', CrmEmailMarketingStatus::UNSUBSCRIBED],
            'unknown words' => ['moved away', CrmEmailMarketingStatus::UNSUBSCRIBED],
            'mailchimp cleaned' => ['cleaned', CrmEmailMarketingStatus::BOUNCED],
            'hard bounce' => [' Hard Bounce ', CrmEmailMarketingStatus::BOUNCED],
            'spam' => ['SPAM', CrmEmailMarketingStatus::COMPLAINED],
            'complaint' => ['complaint', CrmEmailMarketingStatus::COMPLAINED],
        ];
    }

    #[DataProvider('reasons')]
    public function test_suppression_reasons_map_common_export_values(string $value, CrmEmailMarketingStatus $expected): void
    {
        $this->assertSame($expected, ContactImportParser::suppressionReason($value));
    }
}
