<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Tags;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Tag\DeleteTagHandler;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeleteTagAction extends BaseCrmAction
{
    public function __construct(
        private readonly DeleteTagHandler $handler,
    ) {}

    public function __invoke(int $organizerId, int $tagId): Response|JsonResponse
    {
        $this->authorizeCrm($organizerId);

        try {
            $this->handler->handle($organizerId, $tagId);
        } catch (ResourceConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->deletedResponse();
    }
}
