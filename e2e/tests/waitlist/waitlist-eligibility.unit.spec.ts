import { expect, test } from '@playwright/test';
import { canJoinWaitlist } from '../../../frontend/src/utilites/waitlist';
import type { Product, ProductPrice } from '../../../frontend/src/types';

const product = (overrides: Partial<Product> = {}): Product => ({ waitlist_enabled: true, ...overrides } as Product);
const price = (overrides: Partial<ProductPrice> = {}): ProductPrice => ({ ...overrides } as ProductPrice);

test.describe('waitlist eligibility', () => {
  test('a sold-out ticket on sale offers the waitlist', () => {
    expect(canJoinWaitlist(product(), price())).toBe(true);
    expect(canJoinWaitlist(product())).toBe(true);
  });

  test('the waitlist is not offered when the organizer has not enabled it', () => {
    expect(canJoinWaitlist(product({ waitlist_enabled: false }), price())).toBe(false);
  });

  test('the waitlist is not offered once the ticket or its price is outside its sale window', () => {
    expect(canJoinWaitlist(product({ is_after_sale_end_date: true }), price())).toBe(false);
    expect(canJoinWaitlist(product({ is_before_sale_start_date: true }), price())).toBe(false);
    expect(canJoinWaitlist(product(), price({ is_after_sale_end_date: true }))).toBe(false);
    expect(canJoinWaitlist(product(), price({ is_before_sale_start_date: true }))).toBe(false);
  });
});
