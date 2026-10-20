import { type Locator, type Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { PublicEventPage } from '../../pages/public-event.page';
import { createSoldOutEvent } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';

const fillIfNeeded = async (locator: Locator, value: string): Promise<void> => {
  if ((await locator.isEditable()) && (await locator.inputValue()) === '') {
    await locator.fill(value);
  }
};

const fillCheckoutContacts = async (page: Page, details: { firstName: string; email: string }): Promise<void> => {
  const fields: [RegExp, string][] = [
    [/^First Name/, details.firstName],
    [/^Last Name/, 'Journey'],
    [/^Email Address/, details.email],
    [/^Confirm Email Address/, details.email],
  ];
  for (const [label, value] of fields) {
    const inputs = page.getByLabel(label);
    const count = await inputs.count();
    for (let index = 0; index < count; index += 1) {
      await fillIfNeeded(inputs.nth(index), value);
    }
  }
};

test.describe('waitlist journey', () => {
  test('a waitlisted buyer is offered a freed spot and completes the purchase', async ({
    page,
    api,
    account,
    publicApi,
    mailpit,
  }) => {
    const event = await createSoldOutEvent(api, publicApi, account.organizerId, { waitlist: true });
    const waitlisterEmail = uniqueEmail('waitlister');

    const publicPage = new PublicEventPage(page);
    await publicPage.goto(event.eventId, event.slug);
    await publicPage.joinWaitlist({ firstName: 'Wanda', email: waitlisterEmail });

    await expect(page.getByText("You're on the waitlist!")).toBeVisible();
    await publicPage.closeWaitlistSuccessModal();
    await mailpit.waitForMessage(waitlisterEmail, { subjectContains: "You're on the waitlist" });

    const consumedOrderId = await api.findOrderIdByShortId(event.eventId, event.consumedOrder.orderShortId);
    await api.cancelOrder(event.eventId, consumedOrderId);

    const offerUrl = await mailpit.waitForLink(waitlisterEmail, /\/checkout\/\d+/, {
      subjectContains: 'spot has opened up',
    });
    await page.goto(offerUrl.pathname + offerUrl.search);
    await page.waitForLoadState('networkidle');

    await fillCheckoutContacts(page, { firstName: 'Wanda', email: waitlisterEmail });
    await page.getByRole('button', { name: 'Complete Order' }).click();
    await page.waitForURL(/\/checkout\/\d+\/[^/]+\/summary/);
    await page.reload();

    await expect(page.getByText(`You're going to ${event.title}`)).toBeVisible();
    await mailpit.waitForMessage(waitlisterEmail, { subjectContains: 'Your Order is Confirmed' });
  });
});
