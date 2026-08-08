<?php

declare(strict_types=1);

namespace HiEvents\OpenApi;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

class PaginationQueryParametersExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $methodNode = $routeInfo->methodNode();

        if ($methodNode === null) {
            return;
        }

        if ($this->callsMethod($methodNode, 'getPaginationQueryParams')) {
            $operation->addParameters($this->paginationParameters());
        }

        if ($this->callsMethod($methodNode, 'isIncludeRequested')) {
            $operation->addParameters([
                Parameter::make('include', 'query')
                    ->setSchema(Schema::fromType(new StringType))
                    ->description('Comma-separated list of relations to include in the response.'),
            ]);
        }
    }

    private function callsMethod(ClassMethod $methodNode, string $methodName): bool
    {
        return (new NodeFinder)->findFirst(
            (array) $methodNode->stmts,
            static fn (Node $node): bool => $node instanceof MethodCall
                && $node->name instanceof Identifier
                && $node->name->name === $methodName,
        ) !== null;
    }

    /**
     * @return Parameter[]
     */
    private function paginationParameters(): array
    {
        return [
            Parameter::make('page', 'query')
                ->setSchema(Schema::fromType((new IntegerType)->default(1))),
            Parameter::make('per_page', 'query')
                ->setSchema(Schema::fromType((new IntegerType)->default(25))),
            Parameter::make('sort_by', 'query')
                ->setSchema(Schema::fromType(new StringType))
                ->description('Sortable fields are listed in the response `meta.allowed_sorts`.'),
            Parameter::make('sort_direction', 'query')
                ->setSchema(Schema::fromType((new StringType)->enum(['asc', 'desc']))),
            Parameter::make('query', 'query')
                ->setSchema(Schema::fromType(new StringType))
                ->description('Search term used to filter the results.'),
            Parameter::make('filter_fields', 'query')
                ->setSchema(Schema::fromType(
                    (new ObjectType)->additionalProperties((new ObjectType)->additionalProperties(new StringType)),
                ))
                ->setStyle('deepObject')
                ->setExplode(true)
                ->description('Filters in the form `filter_fields[field][operator]=value`. Filterable fields are listed in the response `meta.allowed_filter_fields`.'),
        ];
    }
}
