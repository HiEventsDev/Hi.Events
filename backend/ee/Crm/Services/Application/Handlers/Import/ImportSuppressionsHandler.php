<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Import;

use HiEvents\DomainObjects\CrmImportDomainObject;
use HiEvents\DomainObjects\Enums\CrmImportKind;
use HiEvents\Enterprise\Crm\Exceptions\InvalidContactImportException;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\ImportSuppressionsDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportParser;
use HiEvents\Enterprise\Crm\Services\Domain\Import\ContactImportRunner;
use HiEvents\Enterprise\Crm\Services\Domain\Import\DTO\ContactImportRunDTO;
use Throwable;

class ImportSuppressionsHandler
{
    public function __construct(
        private readonly ContactImportParser $parser,
        private readonly ContactImportRunner $runner,
    ) {}

    /**
     * @throws InvalidContactImportException
     * @throws Throwable
     */
    public function handle(ImportSuppressionsDTO $dto): CrmImportDomainObject
    {
        $parsed = $this->parser->parse($dto->file_path);

        return $this->runner->start(
            new ContactImportRunDTO(
                account_id: $dto->account_id,
                organizer_id: $dto->organizer_id,
                user_id: $dto->user_id,
                file_name: $dto->file_name,
                kind: CrmImportKind::SUPPRESSIONS,
            ),
            $parsed,
        );
    }
}
