<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Import;

use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Exceptions\InvalidContactImportException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSuppressionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\SuppressionImportPreviewDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportParser;

class PreviewSuppressionImportHandler
{
    private const SAMPLE_SIZE = 5;

    public function __construct(
        private readonly ContactImportParser $parser,
        private readonly CrmSuppressionRepositoryInterface $suppressionRepository,
        private readonly DeletedContactHasher $deletedContactHasher,
    ) {}

    /**
     * @throws InvalidContactImportException
     */
    public function handle(int $organizerId, string $filePath): SuppressionImportPreviewDTO
    {
        $parsed = $this->parser->parse($filePath);
        $emails = array_keys($parsed->rows);
        $bouncedEmails = array_keys(array_filter(
            $parsed->rows,
            static fn (array $row) => $row['reason'] === CrmEmailMarketingStatus::BOUNCED,
        ));
        $matched = $this->suppressionRepository->countMatchingContacts($organizerId, $emails, $bouncedEmails);
        $deleted = count($this->suppressionRepository->findTombstoneSuppressions($organizerId, $emails, $this->deletedContactHasher->salt($organizerId)));
        $reasons = array_fill_keys([
            CrmEmailMarketingStatus::UNSUBSCRIBED->value,
            CrmEmailMarketingStatus::BOUNCED->value,
            CrmEmailMarketingStatus::COMPLAINED->value,
        ], 0);

        foreach ($parsed->rows as $row) {
            $reasons[$row['reason']->value]++;
        }

        return new SuppressionImportPreviewDTO(
            total_rows: $parsed->total_rows,
            valid_rows: count($parsed->rows),
            invalid_rows: $parsed->invalid_rows,
            duplicate_rows: $parsed->total_rows - $parsed->invalid_rows - count($parsed->rows),
            new_contacts: max(count($emails) - $matched->emails - $deleted, 0),
            existing_contacts: $matched->contacts,
            subscribed_contacts: $matched->subscribed,
            deleted_contacts: $deleted,
            reasons: $reasons,
            sample: array_map(static fn (array $row) => [
                'email' => $row['email'],
                'reason' => $row['reason']->value,
            ], array_slice(array_values($parsed->rows), 0, self::SAMPLE_SIZE)),
        );
    }
}
