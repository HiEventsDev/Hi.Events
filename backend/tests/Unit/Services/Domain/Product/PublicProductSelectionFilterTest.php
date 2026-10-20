<?php

namespace Tests\Unit\Services\Domain\Product;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Services\Domain\Product\PublicProductSelectionFilter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PublicProductSelectionFilterTest extends TestCase
{
    private PublicProductSelectionFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new PublicProductSelectionFilter;
    }

    public function test_returns_everything_when_no_selection_is_given(): void
    {
        $result = $this->filter->filter($this->categories(), [], []);

        $this->assertSame([1 => [10, 11], 2 => [20, 21], 3 => []], $this->summarise($result));
    }

    public function test_keeps_only_the_selected_categories_even_when_empty(): void
    {
        $result = $this->filter->filter($this->categories(), [], [3, 2, 999]);

        $this->assertSame([2 => [20, 21], 3 => []], $this->summarise($result));
    }

    public function test_keeps_selected_products_with_their_addons_and_drops_empty_categories(): void
    {
        $result = $this->filter->filter($this->categories(), [11], []);

        $this->assertSame([1 => [11], 2 => [21]], $this->summarise($result));
    }

    public function test_applies_product_selection_within_the_selected_categories_only(): void
    {
        $result = $this->filter->filter($this->categories(), [11, 20], [1]);

        $this->assertSame([1 => [11], 2 => [21]], $this->summarise($result));
    }

    public function test_category_selection_keeps_addons_that_live_in_other_categories(): void
    {
        $result = $this->filter->filter($this->categories(), [], [1]);

        $this->assertSame([1 => [10, 11], 2 => [21]], $this->summarise($result));
    }

    public function test_unknown_product_ids_leave_nothing(): void
    {
        $result = $this->filter->filter($this->categories(), [404], []);

        $this->assertSame([], $this->summarise($result));
    }

    /**
     * @return Collection<int, ProductCategoryDomainObject>
     */
    private function categories(): Collection
    {
        $addon = $this->product(21, 2);

        return collect([
            $this->category(1, [
                $this->product(10, 1),
                $this->product(11, 1)->setAddons(collect([$addon])),
            ]),
            $this->category(2, [
                $this->product(20, 2),
                $addon,
            ]),
            $this->category(3, []),
        ]);
    }

    private function category(int $id, array $products): ProductCategoryDomainObject
    {
        $category = (new ProductCategoryDomainObject)->setId($id);
        $category->setProducts(collect($products));

        return $category;
    }

    private function product(int $id, int $categoryId): ProductDomainObject
    {
        return (new ProductDomainObject)->setId($id)->setProductCategoryId($categoryId);
    }

    private function summarise(Collection $categories): array
    {
        return $categories
            ->mapWithKeys(fn (ProductCategoryDomainObject $category) => [
                $category->getId() => $category->getProducts()->map(fn (ProductDomainObject $product) => $product->getId())->values()->all(),
            ])
            ->all();
    }
}
