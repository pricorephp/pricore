<?php

namespace App\Services\OpenApi;

use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Infer\Definition\PropertyVisibility;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Generator\ClassBasedReference;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\PropertyFetchReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\LaravelData\Data;

/**
 * Documents Spatie Data objects by their public properties, which is what they
 * serialize to. Without it, Scramble only sees an arbitrary Arrayable.
 */
class DataToSchema extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && is_a($type->name, Data::class, true);
    }

    /**
     * @param  ObjectType  $type
     */
    public function toSchema(Type $type): ?OpenApiType
    {
        $definition = $this->infer->index->getClass($type->name);

        if (! $definition) {
            return null;
        }

        $items = [];

        foreach ($definition->getData()->properties as $name => $propertyDefinition) {
            if ($propertyDefinition->visibility !== PropertyVisibility::Public) {
                continue;
            }

            $item = new ArrayItemType_(
                $name,
                // The declared type: an instance type would carry whatever was passed in at one call site.
                ReferenceTypeResolver::getInstance()->resolve(new GlobalScope, new PropertyFetchReferenceType(new ObjectType($type->name), $name)),
            );

            if ($docNode = $propertyDefinition->getDocNode()) {
                $item->setAttribute('docNode', $docNode);
            }

            $items[] = $item;
        }

        return $this->openApiTransformer->transform(new KeyedArrayType($items));
    }

    public function reference(ObjectType $type): Reference
    {
        return ClassBasedReference::create('schemas', $type->name, $this->components);
    }
}
