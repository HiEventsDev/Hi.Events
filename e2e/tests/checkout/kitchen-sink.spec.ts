import { test } from '../../fixtures';
import { arrangeKitchenSinkEvent, runKitchenSinkCheckout, type KitchenSinkTotals } from './kitchen-sink.shared';
import { STRIPE_PUBLIC_KEY } from '../../utils/env';
import { nonSaasOnly } from '../../utils/mode';

const TOTALS: KitchenSinkTotals = {
  standardBase: '$25.00',
  standardInclusive: '$30.25',
  subtotal: '$127.50',
  fees: '$5.00',
  taxes: '$4.00',
  total: '$136.50',
};

test.describe('kitchen sink checkout', () => {
  test('a buyer completes the kitchen-sink checkout with offline payment', async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();

    const scenario = await arrangeKitchenSinkEvent(api, account.organizerId);
    await runKitchenSinkCheckout(page, scenario, { api, publicApi, mailpit }, {
      paymentMode: 'offline',
      attendeeCollection: 'PER_ATTENDEE',
      totals: TOTALS,
    });
  });

  test.describe(() => {
    test.skip(!STRIPE_PUBLIC_KEY, 'Requires STRIPE_PUBLIC_KEY (Stripe test mode) to be configured.');
    nonSaasOnly();

    test('a buyer completes the kitchen-sink checkout with a Stripe card payment', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
      test.slow();

      const scenario = await arrangeKitchenSinkEvent(api, account.organizerId);
      await runKitchenSinkCheckout(page, scenario, { api, publicApi, mailpit }, {
        paymentMode: 'stripe',
        attendeeCollection: 'PER_ATTENDEE',
        totals: TOTALS,
      });
    });
  });
});
