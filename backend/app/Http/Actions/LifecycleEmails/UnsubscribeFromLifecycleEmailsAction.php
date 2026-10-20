<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\LifecycleEmails;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\LifecycleEmail\UnsubscribeFromLifecycleEmailsHandler;
use Illuminate\Http\Response;

class UnsubscribeFromLifecycleEmailsAction extends BaseAction
{
    public function __construct(
        private readonly UnsubscribeFromLifecycleEmailsHandler $handler,
    ) {}

    public function __invoke(string $token): Response
    {
        try {
            $this->handler->handle($token);
        } catch (ResourceNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->noContentResponse();
    }
}
