<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Tags;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Http\Request\UpsertTagRequest;
use HiEvents\Enterprise\Crm\Resources\TagResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\DTO\UpsertTagDTO;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\UpsertTagHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpdateTagAction extends BaseCrmAction
{
    public function __construct(
        private readonly UpsertTagHandler $handler,
    ) {}

    public function __invoke(UpsertTagRequest $request, int $organizerId, int $tagId): JsonResponse
    {
        $this->authorizeCrmChange($organizerId);

        try {
            $tag = $this->handler->handle(new UpsertTagDTO(
                account_id: $this->getAuthenticatedAccountId(),
                organizer_id: $organizerId,
                name: $request->validated('name'),
                color: $request->validated('color'),
                tag_id: $tagId,
            ));
        } catch (ResourceConflictException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }

        return $this->resourceResponse(TagResource::class, $tag);
    }
}
