<?php

declare(strict_types=1);

namespace HiEvents\OpenApi;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

class ErrorResponsesExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = collect($routeInfo->route->gatherMiddleware())->filter(fn ($m) => is_string($m));

        $existingCodes = collect($operation->responses)
            ->filter(static fn ($response) => $response instanceof Response)
            ->map(static fn (Response $response) => $response->code);

        if ($middleware->contains(fn (string $m) => Str::is(['auth', 'auth:*'], $m)) && ! $existingCodes->contains(403)) {
            $operation->addResponse($this->messageResponse(
                403,
                'The authenticated user is not allowed to perform this action.',
            ));
        }

        if ($routeInfo->route->parameterNames() !== [] && ! $existingCodes->contains(404)) {
            $operation->addResponse($this->messageResponse(
                404,
                'The requested resource was not found.',
            ));
        }

        if ($middleware->contains(fn (string $m) => str_starts_with($m, 'throttle:')) && ! $existingCodes->contains(429)) {
            $operation->addResponse($this->messageResponse(
                429,
                'Too many requests. Retry once the rate limit window resets.',
            ));
        }
    }

    private function messageResponse(int $statusCode, string $description): Response
    {
        $schema = (new ObjectType)
            ->addProperty('message', new StringType)
            ->setRequired(['message']);

        return Response::make($statusCode)
            ->description($description)
            ->setContent('application/json', Schema::fromType($schema));
    }
}
