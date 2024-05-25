<?php

namespace HiEvents\DomainObjects\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface DomainObjectInterface
{
    public function toArray(): array;

    public static function hydrate($data): DomainObjectInterface;

    public static function hydrateFromModel(Model $model): DomainObjectInterface;

    public static function hydrateFromArray(array $array): DomainObjectInterface;
}
