<?php

namespace HiEvents\DomainObjects\Generated;

/**
 * THIS FILE IS AUTOGENERATED - DO NOT EDIT IT DIRECTLY.
 * @package HiEvents\DomainObjects\Generated
 */
abstract class ProductPriceDomainObjectAbstract extends \HiEvents\DomainObjects\AbstractDomainObject
{
    final public const SINGULAR_NAME = 'product_price';
    final public const PLURAL_NAME = 'product_prices';
    final public const ID = 'id';
    final public const PRODUCT_ID = 'product_id';
    final public const PRICE = 'price';
    final public const LABEL = 'label';
    final public const SALE_START_DATE = 'sale_start_date';
    final public const SALE_END_DATE = 'sale_end_date';
    final public const CREATED_AT = 'created_at';
    final public const UPDATED_AT = 'updated_at';
    final public const DELETED_AT = 'deleted_at';
    final public const INITIAL_QUANTITY_AVAILABLE = 'initial_quantity_available';
    final public const QUANTITY_SOLD = 'quantity_sold';
    final public const IS_HIDDEN = 'is_hidden';
    final public const ORDER = 'order';
    final public const QUANTITY_AVAILABLE = 'quantity_available';

    protected int $id;
    protected int $product_id;
    protected float $price;
    protected ?string $label = null;
    protected ?string $sale_start_date = null;
    protected ?string $sale_end_date = null;
    protected string $created_at;
    protected ?string $updated_at = null;
    protected ?string $deleted_at = null;
    protected ?int $initial_quantity_available = null;
    protected int $quantity_sold = 0;
    protected ?bool $is_hidden = false;
    protected int $order = 1;
    protected ?int $quantity_available = null;

    public function toArray(): array
    {
        return [
                    'id' => $this->id ?? null,
                    'product_id' => $this->product_id ?? null,
                    'price' => $this->price ?? null,
                    'label' => $this->label ?? null,
                    'sale_start_date' => $this->sale_start_date ?? null,
                    'sale_end_date' => $this->sale_end_date ?? null,
                    'created_at' => $this->created_at ?? null,
                    'updated_at' => $this->updated_at ?? null,
                    'deleted_at' => $this->deleted_at ?? null,
                    'initial_quantity_available' => $this->initial_quantity_available ?? null,
                    'quantity_sold' => $this->quantity_sold ?? null,
                    'is_hidden' => $this->is_hidden ?? null,
                    'order' => $this->order ?? null,
                    'quantity_available' => $this->quantity_available ?? null,
                ];
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setProductId(int $product_id): self
    {
        $this->product_id = $product_id;
        return $this;
    }

    public function getProductId(): int
    {
        return $this->product_id;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setSaleStartDate(?string $sale_start_date): self
    {
        $this->sale_start_date = $sale_start_date;
        return $this;
    }

    public function getSaleStartDate(): ?string
    {
        return $this->sale_start_date;
    }

    public function setSaleEndDate(?string $sale_end_date): self
    {
        $this->sale_end_date = $sale_end_date;
        return $this;
    }

    public function getSaleEndDate(): ?string
    {
        return $this->sale_end_date;
    }

    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getCreatedAt(): string
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

    public function setInitialQuantityAvailable(?int $initial_quantity_available): self
    {
        $this->initial_quantity_available = $initial_quantity_available;
        return $this;
    }

    public function getInitialQuantityAvailable(): ?int
    {
        return $this->initial_quantity_available;
    }

    public function setQuantitySold(int $quantity_sold): self
    {
        $this->quantity_sold = $quantity_sold;
        return $this;
    }

    public function getQuantitySold(): int
    {
        return $this->quantity_sold;
    }

    public function setIsHidden(?bool $is_hidden): self
    {
        $this->is_hidden = $is_hidden;
        return $this;
    }

    public function getIsHidden(): ?bool
    {
        return $this->is_hidden;
    }

    public function setOrder(int $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setQuantityAvailable(?int $quantity_available): self
    {
        $this->quantity_available = $quantity_available;
        return $this;
    }

    public function getQuantityAvailable(): ?int
    {
        return $this->quantity_available;
    }
}
