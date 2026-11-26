<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Imports;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactImportResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Import\GetContactImportsHandler;
use Illuminate\Http\JsonResponse;

class GetContactImportsAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetContactImportsHandler $handler,
    ) {}

    public function __invoke(int $organizerId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(ContactImportResource::class, $this->handler->handle($organizerId));
    }
}
