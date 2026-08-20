<?php

declare(strict_types=1);

namespace HiEvents\OpenApi;

use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Reference\StaticMethodCallReferenceType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\TypeManagers\ResourceCollectionTypeManager;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Collection;

class BaseActionResponseTypeExtension implements MethodReturnTypeExtension
{
    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(BaseAction::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        return match ($event->name) {
            'resourceResponse', 'filterableResourceResponse' => $this->getResourceResponseType($event),
            'jsonResponse' => $this->getJsonResponseType($event),
            'errorResponse' => $this->getErrorResponseType($event),
            'addTokenToResponse' => $event->getArg('response', 0),
            default => null,
        };
    }

    private function getResourceResponseType(MethodCallEvent $event): ?Type
    {
        $resourceType = $event->getArg('resource', 0);

        if (! $resourceType instanceof LiteralString || $resourceType->getValue() === '') {
            return null;
        }

        $dataType = $this->unwrapDataType($event->getArg('data', 1));

        if ($dataType === null) {
            return null;
        }

        $statusPosition = $event->name === 'filterableResourceResponse' ? 3 : 2;

        $resolvedResource = $this->resolveResourceType($event, $resourceType->getValue(), $dataType);

        if (! $resolvedResource instanceof ObjectType) {
            return null;
        }

        $responseWrapper = $resolvedResource->isInstanceOf(ResourceCollection::class)
            ? ResourceCollectionTypeManager::make($resolvedResource)->getResponseType()
            : new Generic(ResourceResponse::class, [$resolvedResource]);

        return new Generic(JsonResponse::class, [
            $responseWrapper,
            $this->getStatusType($event, $statusPosition, ResponseCodes::HTTP_OK),
            new KeyedArrayType,
        ]);
    }

    private function getJsonResponseType(MethodCallEvent $event): Type
    {
        $dataType = $event->getArg('data', 0);
        $wrapType = $event->getArg('wrapInData', 2, new LiteralBooleanType(false));

        $bodyType = $wrapType instanceof LiteralBooleanType && $wrapType->value
            ? new KeyedArrayType([new ArrayItemType_('data', $dataType)])
            : $dataType;

        return new Generic(JsonResponse::class, [
            $bodyType,
            $this->getStatusType($event, 1, ResponseCodes::HTTP_OK),
            new KeyedArrayType,
        ]);
    }

    private function getErrorResponseType(MethodCallEvent $event): Type
    {
        return new Generic(JsonResponse::class, [
            new KeyedArrayType([
                new ArrayItemType_('message', new StringType),
                new ArrayItemType_('errors', $event->getArg('errors', 2, new ArrayType)),
            ]),
            $this->getStatusType($event, 1, ResponseCodes::HTTP_BAD_REQUEST),
            new KeyedArrayType,
        ]);
    }

    private function getStatusType(MethodCallEvent $event, int $position, int $default): LiteralIntegerType
    {
        $statusType = $event->getArg('statusCode', $position, new LiteralIntegerType($default));

        return $statusType instanceof LiteralIntegerType ? $statusType : new LiteralIntegerType($default);
    }

    private function unwrapDataType(Type $dataType): ?ObjectType
    {
        if ($dataType instanceof Union) {
            foreach ($dataType->types as $memberType) {
                if ($memberType instanceof ObjectType) {
                    return $memberType;
                }
            }

            return null;
        }

        return $dataType instanceof ObjectType ? $dataType : null;
    }

    private function resolveResourceType(MethodCallEvent $event, string $resourceClass, ObjectType $dataType): Type
    {
        $isCollectionLike = $dataType->isInstanceOf(Collection::class)
            || $dataType->isInstanceOf(Paginator::class);

        $reference = $isCollectionLike
            ? new StaticMethodCallReferenceType($resourceClass, 'collection', [$dataType])
            : new NewCallReferenceType($resourceClass, [$dataType]);

        return ReferenceTypeResolver::getInstance()->resolve($event->scope, $reference);
    }
}
