import { expect, test } from '@playwright/test';
import { getBeforeDiscountDisplayPrice } from '../../../frontend/src/components/routes/product-widget/SelectProducts/priceTotals';
import { ProductPrice } from '../../../frontend/src/types';

test.describe('inclusive struck price from the server', () => {
  const cloudDefaultPromos = [
    {
      name: 'no tax',
      price: { price: 18.0, tax_total: 0, fee_total: 0.27, price_before_discount_including_taxes_and_fees: 20.3 },
      strike: 20.3,
    },
    {
      name: '23% VAT',
      price: { price: 18.0, tax_total: 4.14, fee_total: 0.34, price_before_discount_including_taxes_and_fees: 24.97 },
      strike: 24.97,
    },
  ];

  for (const promo of cloudDefaultPromos) {
    test(`a 10% promo on a $20 Cloud default event (${promo.name}) strikes the full inclusive price`, () => {
      const price: ProductPrice = { id: 100, price_before_discount: 20.0, is_discounted: true, ...promo.price };

      expect(getBeforeDiscountDisplayPrice(price, 'INCLUSIVE')).toBe(promo.strike);
      expect(getBeforeDiscountDisplayPrice(price, 'EXCLUSIVE')).toBe(20.0);
      expect(getBeforeDiscountDisplayPrice(price, undefined)).toBe(20.0);
    });
  }

  test('a full discount still strikes the full inclusive price', () => {
    const price: ProductPrice = {
      id: 100,
      price: 0,
      price_before_discount: 20.0,
      price_before_discount_including_taxes_and_fees: 24.97,
      is_discounted: true,
      tax_total: 0,
      fee_total: 0,
    };

    expect(getBeforeDiscountDisplayPrice(price, 'INCLUSIVE')).toBe(24.97);
  });

  test('the server value is used even when the published totals cannot be explained locally', () => {
    const price: ProductPrice = {
      id: 100,
      price: 9.0,
      price_before_discount: 10.0,
      price_before_discount_including_taxes_and_fees: 10.65,
      is_discounted: true,
      tax_total: 0,
      fee_total: 0.58,
    };

    expect(getBeforeDiscountDisplayPrice(price, 'INCLUSIVE')).toBe(10.65);
  });

  test('without the server value there is no inclusive strike', () => {
    const discounted: ProductPrice = {
      id: 100,
      price: 18.0,
      price_before_discount: 20.0,
      is_discounted: true,
      tax_total: 0,
      fee_total: 0.27,
    };

    expect(getBeforeDiscountDisplayPrice(discounted, 'INCLUSIVE')).toBeNull();
    expect(getBeforeDiscountDisplayPrice({ ...discounted, price_before_discount_including_taxes_and_fees: null }, 'INCLUSIVE')).toBeNull();
    expect(getBeforeDiscountDisplayPrice(discounted, 'EXCLUSIVE')).toBe(20.0);
  });

  test('an undiscounted price has no struck price even if a value is present', () => {
    const price: ProductPrice = {
      id: 100,
      price: 20.0,
      price_before_discount_including_taxes_and_fees: 24.97,
      tax_total: 4.6,
      fee_total: 0.37,
    };

    expect(getBeforeDiscountDisplayPrice(price, 'INCLUSIVE')).toBeNull();
  });
});
