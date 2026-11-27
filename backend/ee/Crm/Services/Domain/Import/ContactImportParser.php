<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Import;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Exceptions\InvalidContactImportException;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Import\DTO\ParsedContactImportDTO;
use HiEvents\Validators\Rules\PersonNameRule;

class ContactImportParser
{
    public const MAX_ROWS = 20000;

    private const EMAIL_HEADERS = ['email', 'e-mail', 'email address', 'e-mail address', 'emailaddress'];

    private const FIRST_NAME_HEADERS = ['first name', 'firstname', 'first_name', 'given name', 'forename'];

    private const LAST_NAME_HEADERS = ['last name', 'lastname', 'last_name', 'surname', 'family name'];

    private const FULL_NAME_HEADERS = ['name', 'full name', 'fullname', 'full_name'];

    private const REASON_HEADERS = ['reason', 'suppression reason', 'suppression_reason', 'status'];

    private const BOUNCE_REASONS = ['bounced', 'bounce', 'hard bounce', 'hard_bounce', 'hardbounce', 'cleaned', 'invalid'];

    private const COMPLAINT_REASONS = ['complained', 'complaint', 'spam', 'spam complaint', 'spam_complaint', 'abuse'];

    private const MAX_NAME_LENGTH = 50;

    private const MAX_EMAIL_LENGTH = 254;

    public function __construct(
        private readonly ContactEmailNormalizer $emailNormalizer,
    ) {}

    /**
     * @throws InvalidContactImportException
     */
    public function parse(string $path): ParsedContactImportDTO
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidContactImportException(__('We could not read that file.'));
        }

        try {
            $delimiter = $this->detectDelimiter($handle);
            $header = fgetcsv($handle, null, $delimiter, '"', '');

            if (! is_array($header)) {
                throw new InvalidContactImportException(__('The file is empty.'));
            }

            $columns = array_map(fn ($value) => $this->normalizeHeader($this->toUtf8((string) $value)), $header);
            $emailColumn = $this->findColumn($columns, self::EMAIL_HEADERS);

            if ($emailColumn === null) {
                throw new InvalidContactImportException(__('We could not find an "Email" column. Add a header row with a column named Email.'));
            }

            $firstNameColumn = $this->findColumn($columns, self::FIRST_NAME_HEADERS);
            $lastNameColumn = $this->findColumn($columns, self::LAST_NAME_HEADERS);
            $fullNameColumn = $this->findColumn($columns, self::FULL_NAME_HEADERS);
            $reasonColumn = $this->findColumn($columns, self::REASON_HEADERS);

            $rows = [];
            $total = 0;
            $invalid = 0;

            while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }

                $total++;
                $row = array_map(fn ($value) => $value === null ? null : $this->toUtf8((string) $value), $row);

                if ($total > self::MAX_ROWS) {
                    throw new InvalidContactImportException(__('This file has more than :max rows. Split it into smaller files.', ['max' => self::MAX_ROWS]));
                }

                $email = $this->emailNormalizer->normalize($this->cell($row, $emailColumn));

                if ($email === null || ! $this->passesContactFormEmailRules($email)) {
                    $invalid++;

                    continue;
                }

                $reason = self::suppressionReason($this->cell($row, $reasonColumn));

                if (isset($rows[$email])) {
                    if ($reason->suppressionStrength() > $rows[$email]['reason']->suppressionStrength()) {
                        $rows[$email]['reason'] = $reason;
                    }

                    continue;
                }

                [$firstName, $lastName] = $this->names($row, $firstNameColumn, $lastNameColumn, $fullNameColumn);

                $rows[$email] = [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'reason' => $reason,
                ];
            }

            if ($rows === []) {
                throw new InvalidContactImportException(__('We could not find any valid email addresses in that file.'));
            }

            return new ParsedContactImportDTO(rows: $rows, total_rows: $total, invalid_rows: $invalid);
        } finally {
            fclose($handle);
        }
    }

    public static function suppressionReason(?string $value): CrmEmailMarketingStatus
    {
        $reason = mb_strtolower(trim((string) $value));

        return match (true) {
            in_array($reason, self::COMPLAINT_REASONS, true) => CrmEmailMarketingStatus::COMPLAINED,
            in_array($reason, self::BOUNCE_REASONS, true) => CrmEmailMarketingStatus::BOUNCED,
            default => CrmEmailMarketingStatus::UNSUBSCRIBED,
        };
    }

    private function passesContactFormEmailRules(string $email): bool
    {
        return mb_strlen($email) <= self::MAX_EMAIL_LENGTH
            && (new EmailValidator)->isValid($email, new RFCValidation);
    }

    /**
     * @param  resource  $handle
     */
    private function detectDelimiter($handle): string
    {
        $firstLine = (string) fgets($handle);
        rewind($handle);

        if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
            fseek($handle, 3);
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    private function normalizeHeader(string $header): string
    {
        return mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', $header)));
    }

    /**
     * @param  string[]  $columns
     * @param  string[]  $candidates
     */
    private function findColumn(array $columns, array $candidates): ?int
    {
        foreach ($columns as $index => $column) {
            if (in_array($column, $candidates, true)) {
                return $index;
            }
        }

        return null;
    }

    private function cell(array $row, ?int $column): ?string
    {
        if ($column === null || ! isset($row[$column])) {
            return null;
        }

        $value = trim((string) $row[$column]);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function names(array $row, ?int $firstNameColumn, ?int $lastNameColumn, ?int $fullNameColumn): array
    {
        $firstName = $this->cell($row, $firstNameColumn);
        $lastName = $this->cell($row, $lastNameColumn);

        if ($firstName === null && $lastName === null) {
            $fullName = $this->cell($row, $fullNameColumn);

            if ($fullName !== null) {
                [$firstName, $lastName] = array_pad(preg_split('/\s+/', $fullName, 2) ?: [], 2, null);
            }
        }

        return [$this->safeName($firstName), $this->safeName($lastName)];
    }

    private function safeName(?string $name): ?string
    {
        if ($name === null || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            return null;
        }

        $isRejected = false;
        (new PersonNameRule)->validate('name', $name, static function () use (&$isRejected): void {
            $isRejected = true;
        });

        return $isRejected ? null : $name;
    }
}
