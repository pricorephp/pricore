<?php

namespace App\Services\OpenApi;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * Documents PaginatedDataCollection<array-key, TData> as the `data`, `links`
 * and `meta` envelope it serializes to.
 */
class PaginatedDataCollectionToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof Generic
            && $type->name === PaginatedDataCollection::class
            && $this->itemType($type) !== null;
    }

    /**
     * @param  Generic  $type
     */
    public function toSchema(Type $type): ?OpenApiType
    {
        $itemType = $this->itemType($type);

        if ($itemType === null) {
            return null;
        }

        $nullableString = Union::wrap([new StringType, new NullType]);
        $nullableInteger = Union::wrap([new IntegerType, new NullType]);

        return $this->openApiTransformer->transform(new KeyedArrayType([
            new ArrayItemType_('data', new ArrayType($itemType)),
            new ArrayItemType_('links', new ArrayType(new KeyedArrayType([
                new ArrayItemType_('url', $nullableString),
                new ArrayItemType_('label', new StringType),
                new ArrayItemType_('page', $nullableInteger),
                new ArrayItemType_('active', new BooleanType),
            ]))),
            new ArrayItemType_('meta', new KeyedArrayType([
                new ArrayItemType_('current_page', new IntegerType),
                new ArrayItemType_('first_page_url', new StringType),
                new ArrayItemType_('from', $nullableInteger),
                new ArrayItemType_('last_page', new IntegerType),
                new ArrayItemType_('last_page_url', new StringType),
                new ArrayItemType_('next_page_url', $nullableString),
                new ArrayItemType_('path', new StringType),
                new ArrayItemType_('per_page', new IntegerType),
                new ArrayItemType_('prev_page_url', $nullableString),
                new ArrayItemType_('to', $nullableInteger),
                new ArrayItemType_('total', new IntegerType),
            ])),
        ]));
    }

    /**
     * @param  Generic  $type
     */
    public function toResponse(Type $type): ?Response
    {
        $itemType = $this->itemType($type);

        if ($itemType === null) {
            return null;
        }

        return Response::make(200)
            ->setContent('application/json', Schema::fromType($this->openApiTransformer->transform($type)))
            ->setDescription('Paginated set of `'.class_basename($itemType->name).'`');
    }

    private function itemType(Generic $type): ?ObjectType
    {
        $itemType = $type->templateTypes[1] ?? $type->templateTypes[0] ?? null;

        return $itemType instanceof ObjectType ? $itemType : null;
    }
}
