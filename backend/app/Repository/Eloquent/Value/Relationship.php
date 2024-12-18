<?php

namespace HiEvents\Repository\Eloquent\Value;

use HiEvents\DomainObjects\Interfaces\DomainObjectInterface;
use InvalidArgumentException;

class Relationship
{
    public function __construct(
        private readonly string  $domainObject,
        /**
         * @var Relationship[]|null
         */
        private readonly ?array  $nested = [],

        private readonly ?string $name = null,

        /**
         * @var OrderAndDirection[]
         */
        private readonly array   $orderAndDirections = [],
    )
    {
        $this->validate();
    }

    public function getName(): string
    {
        return $this->name ?? $this->domainObject::PLURAL_NAME;
    }

    public function getNested(): ?array
    {
        return $this->nested;
    }

    public function getDomainObject(): string
    {
        return $this->domainObject;
    }

    public function getOrderAndDirections(): array
    {
        return $this->orderAndDirections;
    }

    public function buildLaravelEagerLoadArray(): array
    {
        $results = [
            $this->getName() => $this->buildOrderAndDirectionEloquentCallback()
        ];

        // If there are nested relationships, build them and merge into the results array
        if ($this->nested) {
            $results = array_merge($results, $this->buildNested($this, ''));
        }

        return $results;
    }

    private function buildNested(Relationship $relationship, string $prefix): array
    {
        $results = [];

        if ($relationship->nested) {
            foreach ($relationship->nested as $nested) {
                $nestedPrefix = $prefix === '' ? $relationship->getName() : $prefix . '.' . $relationship->getName();
                $results[$nestedPrefix . '.' . $nested->getName()] = $nested->buildOrderAndDirectionEloquentCallback();
                $results = array_merge($results, $this->buildNested($nested, $nestedPrefix));
            }
        }

        return $results;
    }

    private function buildOrderAndDirectionEloquentCallback(): callable|array
    {
        if ($this->getOrderAndDirections() === []) {
            return [];
        }

        return function ($query) {
            foreach ($this->orderAndDirections as $orderAndDirection) {
                $query->orderBy($orderAndDirection->getOrder(), $orderAndDirection->getDirection());
            }
        };
    }

    private function validate(): void
    {
        if (!is_subclass_of($this->domainObject, DomainObjectInterface::class)) {
            throw new InvalidArgumentException(
                __('DomainObject must be a valid :interface.', [
                    'interface' => DomainObjectInterface::class,
                ]),
            );
        }

        foreach ($this->nested as $nested) {
            if (!is_a($nested, __CLASS__)) {
                throw new InvalidArgumentException(
                    __('Nested relationships must be an array of Relationship objects.'),
                );
            }
        }

        foreach ($this->orderAndDirections as $orderAndDirection) {
            if (!is_a($orderAndDirection, OrderAndDirection::class)) {
                throw new InvalidArgumentException(
                    __('OrderAndDirections must be an array of OrderAndDirection objects.'),
                );
            }
        }
    }
}
