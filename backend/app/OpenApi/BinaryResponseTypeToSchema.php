<?php

declare(strict_types=1);

namespace HiEvents\OpenApi;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BinaryResponseTypeToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && ($type->isInstanceOf(BinaryFileResponse::class) || $type->isInstanceOf(StreamedResponse::class));
    }

    public function toResponse(Type $type): Response
    {
        return Response::make(200)
            ->description('File download')
            ->setContent(
                'application/octet-stream',
                Schema::fromType((new StringType)->format('binary')),
            );
    }
}
