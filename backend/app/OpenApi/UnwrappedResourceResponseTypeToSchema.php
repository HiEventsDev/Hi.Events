<?php

declare(strict_types=1);

namespace HiEvents\OpenApi;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class UnwrappedResourceResponseTypeToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->isInstanceOf(JsonResponse::class)
            && count($type->templateTypes) >= 2
            && $type->templateTypes[1] instanceof LiteralIntegerType
            && $type->templateTypes[0] instanceof ObjectType
            && $type->templateTypes[0]->isInstanceOf(JsonResource::class);
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type): Response
    {
        return Response::make($type->templateTypes[1]->value)
            ->setContent(
                'application/json',
                Schema::fromType($this->openApiTransformer->transform($type->templateTypes[0])),
            );
    }
}
