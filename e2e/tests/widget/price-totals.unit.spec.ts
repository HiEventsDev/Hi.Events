import { expect, test } from '@playwright/test';
import {
  getBeforeDiscountDisplayPrice,
  getLineTotal,
} from '../../../frontend/src/components/routes/product-widget/SelectProducts/priceTotals';
import {
  Product,
  ProductPrice,
  ProductPriceType,
  ProductType,
  TaxAndFee,
  TaxAndFeeCalculationType,
  TaxAndFeeType,
} from '../../../frontend/src/types';

const vat = (rate: number): TaxAndFee => ({
  id: 2,
  name: 'VAT',
  rate,
  type: TaxAndFeeType.Tax,
  calculation_type: TaxAndFeeCalculationType.Percentage,
  is_default: false,
  is_active: true,
});

const fixedFee = (rate: number, id = 1): TaxAndFee => ({
  id,
  name: id === 0 ? 'Platform Fee' : 'Booking fee',
  rate,
  type: TaxAndFeeType.Fee,
  calculation_type: TaxAndFeeCalculationType.Fixed,
  is_default: false,
  is_active: true,
});

const product = (taxes: TaxAndFee[]): Product => ({
  id: 1,
  title: 'Ticket',
  type: ProductPriceType.Paid,
  product_type: ProductType.Ticket,
  taxes,
});

test.describe('ticket price totals', () => {
  const awkwardVatOrders = [
    { name: 'no promo', price: 9.99, taxTotal: 2.30, orderTotal: 36.86 },
    { name: '15% promo', price: 8.49, taxTotal: 1.95, orderTotal: 31.33 },
    { name: '1.33 fixed promo', price: 8.66, taxTotal: 1.99, orderTotal: 31.96 },
  ];

  for (const order of awkwardVatOrders) {
    test(`inclusive cart total for 3 x ${order.price} with 23% VAT (${order.name}) equals the server order total`, () => {
      const price: ProductPrice = { id: 100, price: order.price, tax_total: order.taxTotal, fee_total: 0 };

      expect(getLineTotal(product([vat(23)]), price, 3, 'INCLUSIVE')).toBe(order.orderTotal);
    });
  }

  test('inclusive cart total for a discounted per-date price with a fixed fee and VAT equals the server order total', () => {
    const price: ProductPrice = {
      id: 100,
      price: 10.0,
      price_before_discount: 12.5,
      is_discounted: true,
      tax_total: 2.65,
      fee_total: 1.5,
    };

    expect(getLineTotal(product([fixedFee(1.5), vat(23)]), price, 3, 'INCLUSIVE')).toBe(42.44);
  });

  test('exclusive cart total is the pre-tax subtotal', () => {
    const price: ProductPrice = { id: 100, price: 10.01, tax_total: 2.3, fee_total: 0 };

    expect(getLineTotal(product([vat(23)]), price, 3, 'EXCLUSIVE')).toBe(30.03);
  });

  test('an unreproducible platform fee falls back to the per-unit inclusive price', () => {
    const price: ProductPrice = { id: 100, price: 10.0, tax_total: 0, fee_total: 0.62 };

    expect(getLineTotal(product([fixedFee(0, 0)]), price, 3, 'INCLUSIVE')).toBe(31.86);
  });

  test('the struck price of a discounted per-date price matches each display mode', () => {
    const price: ProductPrice = {
      id: 100,
      price: 10.0,
      price_before_discount: 12.5,
      price_before_discount_including_taxes_and_fees: 17.22,
      is_discounted: true,
      tax_total: 2.65,
      fee_total: 1.5,
    };

    expect(getBeforeDiscountDisplayPrice(price, 'EXCLUSIVE')).toBe(12.5);

    const inclusiveStrike = getBeforeDiscountDisplayPrice(price, 'INCLUSIVE');
    expect(inclusiveStrike).toBe(17.22);
    expect(inclusiveStrike).toBeGreaterThan(price.price + 2.65 + 1.5);
  });

  test('an undiscounted price has no struck price', () => {
    const price: ProductPrice = { id: 100, price: 9.99, tax_total: 2.3, fee_total: 0 };

    expect(getBeforeDiscountDisplayPrice(price, 'INCLUSIVE')).toBeNull();
  });
});
