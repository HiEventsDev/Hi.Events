import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { PublicOccurrenceSelector } from '../../pages/occurrence.page';
import {
  arrangeKitchenSinkEvent,
  runKitchenSinkCheckout,
  type KitchenSinkCheckoutOptions,
  type KitchenSinkTotals,
  type RecurringKitchenSinkScenario,
} from './kitchen-sink.shared';
import type { ApiClient } from '../../api/api-client';
import type { Occurrence } from '../../api/types';
import { uniqueName } from '../../utils/unique';
import { STRIPE_PUBLIC_KEY } from '../../utils/env';
import { nonSaasOnly } from '../../utils/mode';

const TOTALS: KitchenSinkTotals = {
  standardBase: '$30.00',
  standardInclusive: '$35.75',
  subtotal: '$132.50',
  fees: '$5.00',
  taxes: '$4.50',
  total: '$142.00',
};

const OCCURRENCE_LABEL = 'Dockside Session';
const BASE_STANDARD_INCLUSIVE = '$30.25';

const utcParts = (isoDate: string) => {
  const date = new Date(isoDate);
  return {
    weekday: date.toLocaleString('en-US', { weekday: 'long', timeZone: 'UTC' }),
    month: date.toLocaleString('en-US', { month: 'long', timeZone: 'UTC' }),
    day: date.getUTCDate(),
    year: date.getUTCFullYear(),
  };
};

const dayButtonLabel = (isoDate: string): RegExp => {
  const { weekday, month, day } = utcParts(isoDate);
  return new RegExp(`^${weekday}, ${month} ${day},`);
};

const paneHeaderDay = (isoDate: string): string => {
  const { weekday, month, day } = utcParts(isoDate);
  return `${weekday}, ${month} ${day}`;
};

async function arrangeRecurringKitchenSink(
  api: ApiClient,
  organizerId: number,
): Promise<{ scenario: RecurringKitchenSinkScenario; occurrences: Occurrence[] }> {
  const scenario = await arrangeKitchenSinkEvent(api, organizerId, { eventType: 'RECURRING' });
  const occurrences = [...scenario.occurrences].sort((a, b) => a.start_date.localeCompare(b.start_date));

  const location = await api.createOrganizerLocation(organizerId, {
    name: uniqueName('Warehouse 9'),
    structured_address: {
      venue_name: 'Warehouse 9',
      address_line_1: '9 Dock Road',
      city: 'Brooklyn',
      country: 'US',
    },
  });

  const overridden = occurrences[1];
  await api.updateOccurrence(scenario.eventId, overridden.id, {
    start_date: overridden.start_date,
    end_date: overridden.end_date,
    label: OCCURRENCE_LABEL,
    event_location: { type: 'IN_PERSON', location_id: location.id },
  });
  await api.setOccurrencePriceOverride(scenario.eventId, overridden.id, {
    product_price_id: scenario.standardPriceId,
    price: 30,
  });

  return { scenario, occurrences };
}

function buildCheckoutOptions(
  paymentMode: 'offline' | 'stripe',
  occurrences: Occurrence[],
): KitchenSinkCheckoutOptions {
  const [first, second] = occurrences;
  const secondParts = utcParts(second.start_date);

  const select = async (page: Page) => {
    const selector = new PublicOccurrenceSelector(page);
    const pane = page.locator('.hi-products-pane');
    const paneTime = pane.locator('.hi-slot-header-time');
    const paneLocation = pane.locator('.hi-slot-header-location');
    const standardRow = page.locator('.hi-product-row').filter({ hasText: 'Standard Ticket' });

    await expect(page.getByRole('heading', { name: 'Select a Date & Time' })).toBeVisible();
    for (const occurrence of occurrences) {
      await selector.navigateToMonthOf(occurrence.start_date);
      await expect(selector.dayButton(dayButtonLabel(occurrence.start_date))).toBeVisible();
    }

    await selector.navigateToMonthOf(first.start_date);
    await expect(selector.slotHeaderDay()).toHaveText(paneHeaderDay(first.start_date));
    await expect(paneTime).toContainText(/7:00\s?PM/i);
    await expect(paneLocation).toHaveCount(0);
    await expect(selector.productsLoadingOverlay()).toHaveCount(0);
    await expect(standardRow.getByText(BASE_STANDARD_INCLUSIVE)).toBeVisible();

    const secondLabel = dayButtonLabel(second.start_date);
    await selector.navigateToMonthOf(second.start_date);
    await selector.dayButton(secondLabel).click();
    await expect(selector.slotHeaderDay()).toHaveText(paneHeaderDay(second.start_date));
    await expect(paneTime).toContainText(/7:00\s?PM/i);
    await expect(pane.getByText(OCCURRENCE_LABEL)).toBeVisible();
    await expect(paneLocation).toContainText('Warehouse 9, Brooklyn');
    await expect(selector.productsLoadingOverlay()).toHaveCount(0);
    await expect(standardRow.getByText(TOTALS.standardInclusive)).toBeVisible();
  };

  const expectSummaryDetails = async (page: Page) => {
    const detailItem = (label: string) => page.locator('[class*="detailItem"]').filter({ hasText: label });
    await expect(detailItem('Event Date')).toContainText(new RegExp(`${secondParts.month} ${secondParts.day}\\b`));
    await expect(detailItem('Event Date')).toContainText(OCCURRENCE_LABEL);
    await expect(detailItem('Location')).toContainText('Warehouse 9');
    await expect(detailItem('Location')).toContainText('9 Dock Road');
  };

  return {
    paymentMode,
    attendeeCollection: 'PER_ORDER',
    totals: TOTALS,
    occurrence: { select, expectSummaryDetails },
    emailBodyContains: [`${secondParts.month} ${secondParts.day}, ${secondParts.year}`, '7:00 PM'],
  };
}

test.describe('kitchen sink recurring checkout', () => {
  test('a buyer completes the recurring kitchen-sink checkout with offline payment', async ({ page, api, account, publicApi, mailpit }) => {
    test.slow();

    const { scenario, occurrences } = await arrangeRecurringKitchenSink(api, account.organizerId);
    await runKitchenSinkCheckout(page, scenario, { api, publicApi, mailpit }, buildCheckoutOptions('offline', occurrences));
  });

  test.describe(() => {
    test.skip(!STRIPE_PUBLIC_KEY, 'Requires STRIPE_PUBLIC_KEY (Stripe test mode) to be configured.');
    nonSaasOnly();

    test('a buyer completes the recurring kitchen-sink checkout with a Stripe card payment', { tag: '@stripe' }, async ({ page, api, account, publicApi, mailpit }) => {
      test.slow();

      const { scenario, occurrences } = await arrangeRecurringKitchenSink(api, account.organizerId);
      await runKitchenSinkCheckout(page, scenario, { api, publicApi, mailpit }, buildCheckoutOptions('stripe', occurrences));
    });
  });
});
