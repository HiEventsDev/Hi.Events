<?php

namespace Tests\Unit\Validators\Rules;

use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Validators\Rules\ProductQuestionRule;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductQuestionRuleTest extends TestCase
{
    private function makeRule(): ProductQuestionRule
    {
        $price = new ProductPriceDomainObject;
        $price->setId(100);
        $price->setProductId(1);

        $product = new ProductDomainObject;
        $product->setId(1);
        $product->setProductPrices(new Collection([$price]));

        return new ProductQuestionRule(
            questions: new Collection,
            products: new Collection([$product]),
        );
    }

    public function test_missing_product_price_id_throws_validation_exception_instead_of_erroring(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeRule()->validate(
            attribute: 'products',
            value: [['product_id' => 1]],
            fail: static fn () => null,
        );
    }

    public function test_non_numeric_product_price_id_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeRule()->validate(
            attribute: 'products',
            value: [['product_id' => 1, 'product_price_id' => 'abc']],
            fail: static fn () => null,
        );
    }

    public function test_unknown_product_price_id_throws_validation_exception_instead_of_null_dereference(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeRule()->validate(
            attribute: 'products',
            value: [['product_id' => 1, 'product_price_id' => 999]],
            fail: static fn () => null,
        );
    }
}
