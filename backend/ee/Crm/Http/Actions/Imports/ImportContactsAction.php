<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Imports;

use HiEvents\Enterprise\Crm\Exceptions\InvalidContactImportException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\ImportContactsRequest;
use HiEvents\Enterprise\Crm\Resources\ContactImportResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\ImportContactsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\DTO\ImportSuppressionsDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\ImportContactsHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\ImportSuppressionsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ImportContactsAction extends BaseCrmAction
{
    public function __construct(
        private readonly ImportContactsHandler $handler,
        private readonly ImportSuppressionsHandler $suppressionsHandler,
    ) {}

    public function __invoke(ImportContactsRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        $file = $request->file('file');

        try {
            $import = $request->isSuppressionImport()
                ? $this->suppressionsHandler->handle(new ImportSuppressionsDTO(
                    account_id: $this->getAuthenticatedAccountId(),
                    organizer_id: $organizerId,
                    user_id: $this->getAuthenticatedUser()->getId(),
                    file_path: $file->getRealPath(),
                    file_name: $file->getClientOriginalName(),
                ))
                : $this->handler->handle(new ImportContactsDTO(
                    account_id: $this->getAuthenticatedAccountId(),
                    organizer_id: $organizerId,
                    user_id: $this->getAuthenticatedUser()->getId(),
                    file_path: $file->getRealPath(),
                    file_name: $file->getClientOriginalName(),
                    consent_attested: $request->boolean('consent_attested'),
                    tag_name: $request->validated('tag_name'),
                ));
        } catch (InvalidContactImportException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return $this->resourceResponse(
            ContactImportResource::class,
            $import->setUserName($this->getAuthenticatedUser()->getFullName()),
            Response::HTTP_CREATED,
        );
    }
}
