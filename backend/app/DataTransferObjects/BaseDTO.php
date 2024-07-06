<?php

namespace HiEvents\DataTransferObjects;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Throwable;

abstract class BaseDTO
{
    /**
     * Create a new instance of the DTO from an array.
     *
     * @param array $data
     * @return static
     */
    public static function fromArray(array $data): self
    {
        $reflection = new ReflectionClass(static::class);

        $data = self::removeUnrecognisedKeys($data, $reflection);

        $data = self::handleDtoProperties($reflection, $data);

        $data = static::hydrateObjectsFromProperties($data);

        $data = self::handleEnumProperties($reflection, $data);

        return new static(...$data);
    }

    /**
     * Convert the DTO to an array.
     *
     * @param array $without
     * @return array
     */
    public function toArray(array $without = []): array
    {
        return array_diff_key(get_object_vars($this), array_flip($without));
    }

    /**
     * Create a new Collection of DTOs from an array.
     *
     * @param array $items
     * @return Collection
     */
    public static function collectionFromArray(array $items): Collection
    {
        return collect(array_map([static::class, 'fromArray'], $items));
    }

    /**
     * Hydrate objects from properties based on property to object map.
     *
     * @param array $data
     * @return array
     */
    private static function hydrateObjectsFromProperties(array $data): array
    {
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            if (!array_key_exists($propertyName, $data)) {
                continue;
            }

            $attributes = $property->getAttributes(CollectionOf::class);

            if (count($attributes) > 0) {
                /** @var CollectionOf $collectionOfAttr */
                $collectionOfAttr = $attributes[0]->newInstance();
                $classType = $collectionOfAttr->classType;

                if (is_array($data[$propertyName])) {
                    $data[$propertyName] = collect($data[$propertyName])
                        ->map(fn($item) => $classType::fromArray((array)$item));
                }
            }
        }

        return $data;
    }

    private static function removeUnrecognisedKeys(array $data, ReflectionClass $reflection): array
    {
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $unknownKeys = collect($data)->keys()->diff(collect($properties)->pluck('name'));
        if ($unknownKeys->isNotEmpty()) {
            foreach ($unknownKeys as $unknownKey) {
                unset($data[$unknownKey]);
            }
        }

        return $data;
    }

    private static function handleDtoProperties(ReflectionClass $reflection, array $data): array
    {
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();

            if (!isset($data[$propertyName]) || !is_array($data[$propertyName])) {
                continue;
            }

            $propertyType = $property->getType();
            if ($propertyType === null) {
                continue;
            }

            $propertyTypeName = $propertyType->getName();

            // Check if the property type is a subclass of BaseDTO
            if (is_subclass_of($propertyTypeName, self::class)) {
                $data[$propertyName] = $propertyTypeName::fromArray($data[$propertyName]);
            }
        }

        return $data;
    }

    /**
     * @todo - tidy this up
     */
    private static function handleEnumProperties(ReflectionClass $reflection, array $data): array
    {
        $constructor = $reflection->getConstructor();
        $constructorParams = $constructor ? $constructor->getParameters() : [];

        collect($reflection->getProperties())
            ->each(function (ReflectionProperty $property) use ($constructorParams, $reflection, &$data) {
                $type = $property->getType();
                $enumName = method_exists($type, 'getName') ? $type?->getName() : null;
                $propertyName = $property->getName();

                if (!$enumName) {
                    return;
                }

                $isEnum = enum_exists($property->getType()?->getName()) && method_exists($enumName, 'fromName');

                if (!$isEnum) {
                    return;
                }

                $isMissing = !isset($data[$propertyName]);
                $constructorParam = collect($constructorParams)->firstWhere('name', $propertyName);
                $hasDefaultValue = $constructorParam && $constructorParam->isDefaultValueAvailable();

                if ($isMissing && !$hasDefaultValue) {
                    throw new RuntimeException(
                        sprintf('Missing property [%s] in class [%s]', $property->getName(), static::class)
                    );
                }

                if ($isMissing && $hasDefaultValue) {
                    $data[$propertyName] = $constructorParam->getDefaultValue();
                    return;
                }

                if (is_object($data[$property->getName()])) {
                    return;
                }

                try {
                    $data[$property->getName()] = $enumName::fromName($data[$property->getName()]);
                } catch (Throwable) {
                    throw new RuntimeException(
                        sprintf(
                            'Invalid value [%s] for property [%s] in class [%s]',
                            $data[$property->getName()],
                            $property->getName(),
                            static::class
                        )
                    );
                }
            });

        return $data;
    }
}
