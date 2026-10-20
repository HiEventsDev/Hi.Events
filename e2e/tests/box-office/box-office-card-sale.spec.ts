import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { BoxOfficePage } from '../../pages/box-office.page';
import { createLiveEventWithProduct, type SeededEvent } from '../../api/factory';
import type { ApiClient } from '../../api/api-client';
import { STRIPE_SECRET_KEY } from '../../utils/env';
import { expireOrder, stripeReaderIdFor } from '../../utils/db';
import { nonSaasOnly } from '../../utils/mode';
import { uniqueShort } from '../../utils/unique';

interface CardDoor {
  event: SeededEvent;
  door: BoxOfficePage;
  readerId: number;
}

async function openCardDoor(page: Page, api: ApiClient, organizerId: number): Promise<CardDoor> {
  const event = await createLiveEventWithProduct(api, { organizerId, price: 25, currency: 'EUR' });
  const doorLocation = await api.createOrganizerLocation(organizerId, {
    name: 'Door HQ',
    structured_address: {
      address_line_1: '12 Terminal Street',
      city: 'Dublin',
      state_or_region: 'Leinster',
      zip_or_postal_code: 'D02 AF30',
      country: 'IE',
    },
  });
  await api.setOrganizerLocation(organizerId, doorLocation.id);
  const reader = await api.registerTerminalReader(organizerId, {
    registration_code: 'simulated-s710',
    label: uniqueShort('Sim reader'),
  });
  const boxOffices = await api.listBoxOffices(event.eventId);
  const boxOffice = await api.resetBoxOfficePin(event.eventId, boxOffices.find((candidate) => candidate.is_system_default)!.id);

  const door = new BoxOfficePage(page);
  await door.goto(boxOffice.short_id);
  await page.getByTestId(`box-office-reader-${reader.id}`).click();
  await door.startSession('Sam', boxOffice.pin);
  return { event, door, readerId: reader.id };
}

test.describe('box office card sales', () => {
  test.skip(!STRIPE_SECRET_KEY, 'Requires STRIPE_SECRET_KEY (Stripe test mode) to be configured.');
  nonSaasOnly();

  test('a staff member takes a card payment on a simulated reader', { tag: '@stripe' }, async ({ page, api, account, publicApi }) => {
    test.slow();

    const { event, door, readerId } = await openCardDoor(page, api, account.organizerId);
    await door.addProduct(event.priceId, 1);
    await door.charge();
    await page.getByTestId('box-office-tender-card').click();

    await expect(page.getByText('Present card on reader')).toBeVisible({ timeout: 20_000 });

    const stripeReaderId = stripeReaderIdFor(readerId);
    const presented = await publicApi.post(
      `https://api.stripe.com/v1/test_helpers/terminal/readers/${stripeReaderId}/present_payment_method`,
      { headers: { Authorization: `Bearer ${STRIPE_SECRET_KEY}` } },
    );
    expect(presented.ok(), await presented.text()).toBeTruthy();

    await expect(door.saleCompleteHeading()).toBeVisible({ timeout: 30_000 });
  });

  test('a sale that expired before the card was taken shows the expired state', { tag: '@stripe' }, async ({ page, api, account }) => {
    test.slow();

    const { event, door } = await openCardDoor(page, api, account.organizerId);
    await door.addProduct(event.priceId, 1);
    const created = page.waitForResponse((response) => response.request().method() === 'POST' && /\/public\/box-offices\/[^/]+\/orders$/.test(response.url()));
    await door.charge();
    const order = (await (await created).json()).data as { short_id: string };
    await expect(page.getByTestId('box-office-tender-card')).toBeVisible();

    expireOrder(order.short_id);

    await page.getByTestId('box-office-tender-card').click();
    await expect(page.getByRole('heading', { name: 'Sale expired' })).toBeVisible({ timeout: 20_000 });
    await expect(door.saleCompleteHeading()).toHaveCount(0);
  });
});
