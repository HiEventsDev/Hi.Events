<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Imports;

use HiEvents\Enterprise\Crm\Exceptions\InvalidContactImportException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\PreviewSuppressionImportRequest;
use HiEvents\Enterprise\Crm\Resources\SuppressionImportPreviewResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\PreviewSuppressionImportHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PreviewSuppressionImportAction extends BaseCrmAction
{
    public function __construct(
        private readonly PreviewSuppressionImportHandler $handler,
    ) {}

    public function __invoke(PreviewSuppressionImportRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $preview = $this->handler->handle($organizerId, $request->file('file')->getRealPath());
        } catch (InvalidContactImportException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return $this->resourceResponse(SuppressionImportPreviewResource::class, $preview);
    }
}
