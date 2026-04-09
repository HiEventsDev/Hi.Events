<?php

declare(strict_types=1);

namespace Tests\Unit\Repository\Fixtures;

use HiEvents\DomainObjects\AbstractDomainObject;
use Illuminate\Support\Collection;

class WidgetCategoryDomainObject extends AbstractDomainObject
{
    public const SINGULAR_NAME = 'widget_category';

    public const PLURAL_NAME = 'widget_categories';

    protected ?int $id = null;

    protected ?string $name = null;

    protected ?Collection $widgets = null;

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setWidgets(?Collection $widgets): self
    {
        $this->widgets = $widgets;

        return $this;
    }

    public function getWidgets(): ?Collection
    {
        return $this->widgets;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
