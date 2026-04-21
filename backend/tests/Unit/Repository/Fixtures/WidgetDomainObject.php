<?php

declare(strict_types=1);

namespace Tests\Unit\Repository\Fixtures;

use HiEvents\DomainObjects\AbstractDomainObject;

class WidgetDomainObject extends AbstractDomainObject
{
    public const SINGULAR_NAME = 'widget';

    public const PLURAL_NAME = 'widgets';

    protected ?int $id = null;

    protected ?int $category_id = null;

    protected ?string $name = null;

    protected ?string $sku = null;

    protected ?int $quantity = null;

    protected ?float $price = null;

    protected ?bool $is_active = null;

    protected ?string $description = null;

    protected ?string $created_at = null;

    protected ?string $updated_at = null;

    protected ?string $deleted_at = null;

    protected ?WidgetCategoryDomainObject $category = null;

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCategoryId(?int $category_id): self
    {
        $this->category_id = $category_id;

        return $this;
    }

    public function getCategoryId(): ?int
    {
        return $this->category_id;
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

    public function setSku(?string $sku): self
    {
        $this->sku = $sku;

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setPrice(float|int|null $price): self
    {
        $this->price = $price === null ? null : (float) $price;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setIsActive(?bool $is_active): self
    {
        $this->is_active = $is_active;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->is_active;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setCreatedAt(?string $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updated_at;
    }

    public function setDeletedAt(?string $deleted_at): self
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    public function getDeletedAt(): ?string
    {
        return $this->deleted_at;
    }

    public function setCategory(?WidgetCategoryDomainObject $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): ?WidgetCategoryDomainObject
    {
        return $this->category;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
