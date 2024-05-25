<?php

namespace HiEvents\Repository\Eloquent\Value;

readonly class Relationship
{
    public function __construct(
        private string  $domainObject,
        /**
         * @var Relationship[]|null
         */
        private ?array  $nested = [],

        private ?string $name = null,
    )
    {
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

    public function buildLaravelEagerLoadArray(): array
    {
        if (!$this->nested) {
            return [$this->getName()];
        }

        return $this->buildNested($this, '');
    }

    private function buildNested(Relationship $relationship, string $prefix): array
    {
        $results = [];

        if ($relationship->nested) {
            foreach ($relationship->nested as $nested) {
                $nestedPrefix = $prefix === '' ? $relationship->getName() : $prefix . '.' . $relationship->getName();
                $results[] = $nestedPrefix . '.' . $nested->getName();
                $results = array_merge($results, $this->buildNested($nested, $nestedPrefix));
            }
        }

        return $results;
    }
}
