<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Automations;

use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\PreviewAutomationRequest;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Automation\PreviewAutomationHandler;
use Illuminate\Http\JsonResponse;

class PreviewAutomationAction extends BaseCrmAction
{
    public function __construct(
        private readonly PreviewAutomationHandler $handler,
    ) {}

    public function __invoke(PreviewAutomationRequest $request, int $organizerId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $preview = $this->handler->handle($this->previewFrom($request, $organizerId));
        } catch (InvalidSegmentDefinitionException $exception) {
            throw $this->invalidSegment($exception);
        }

        return $this->jsonResponse($preview->toArray(), wrapInData: true);
    }
}
