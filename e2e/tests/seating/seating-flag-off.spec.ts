import {readFileSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {test as base, expect} from '../../fixtures';
import type {ApiClient} from '../../api/api-client';
import {createDraftEvent, createSeatedEvent} from '../../api/factory';
import {AttendeePage} from '../../pages/attendee.page';
import {CheckoutPage} from '../../pages/checkout.page';
import {SeatPickerPage} from '../../pages/seat-picker.page';
import {simulateLicence, withLicence} from '../../utils/licence';
import {uniqueEmail} from '../../utils/unique';

const test = base.extend<{cloudApi: ApiClient}>({
  cloudApi: async ({playwright, account}, use) => {
    await withLicence(playwright, account.token, 'CLOUD', use);
  },
});

const theatreLayout = () => JSON.parse(
  readFileSync(fileURLToPath(new URL('../../../backend/tests/Fixtures/seating/theatre.json', import.meta.url)), 'utf8'),
);

const NO_SIMULATION = 'The backend ignores licence simulation (APP_ENV is not testing or e2e), so it cannot act as Hi.Events Cloud.';

test.describe('reserved seating with the feature flag off on Hi.Events Cloud', () => {
  test('the seating pages are hidden and the seating API is refused', {tag: '@smoke'}, async ({authedPage: page, adminApi, cloudApi, account}) => {
    await adminApi.setAccountFeatureFlag(await adminApi.findAccountIdByEmail(account.email), 'seating', false);
    test.skip((await cloudApi.getMe()).feature_flags.seating, NO_SIMULATION);
    const event = await createDraftEvent(cloudApi, account.organizerId);
    await simulateLicence(page, 'CLOUD');

    await page.goto(`/manage/event/${event.eventId}/settings`);
    await expect(page.getByRole('link', {name: 'Ticket Designer'})).toBeVisible();
    await expect(page.getByRole('link', {name: 'Seating', exact: true})).toHaveCount(0);

    await page.goto(`/manage/organizer/${account.organizerId}/events`);
    await expect(page.getByRole('link', {name: 'Locations'})).toBeVisible();
    await expect(page.getByRole('link', {name: 'Seat Maps'})).toHaveCount(0);

    await expect(cloudApi.getEventSeatMap(event.eventId)).rejects.toThrow(/→ 404/);
    await expect(cloudApi.createSeatMap(account.organizerId, 'Blocked', theatreLayout())).rejects.toThrow(/→ 403/);
  });

  test('an event keeps selling seats after the flag is turned off', async ({page, adminApi, cloudApi, account}) => {
    const accountId = await adminApi.findAccountIdByEmail(account.email);
    await adminApi.setAccountFeatureFlag(accountId, 'seating', true);
    const event = await createSeatedEvent(cloudApi, account.organizerId);
    await adminApi.setAccountFeatureFlag(accountId, 'seating', false);

    const checkout = new CheckoutPage(page);
    await checkout.gotoPublicEvent(event.eventId, event.slug);
    const picker = new SeatPickerPage(page);
    await picker.selectSeat('e2.0.9');
    await picker.continueToDetails();
    await expect(page.getByText('Premium Seat · Stalls · A-10')).toBeVisible();

    const buyer = {firstName: 'Flag', lastName: 'Off', email: uniqueEmail('flag-off')};
    await checkout.fillOrderDetails(buyer);
    await checkout.fillFirstAttendee(buyer);
    await checkout.completeFreeOrder();
    await expect(page.getByText('Stalls · A-10').filter({visible: true}).first()).toBeVisible();

    expect(await cloudApi.listAttendees(event.eventId)).toEqual([expect.objectContaining({seat_label: 'Stalls · A-10'})]);
  });

  test('an organizer can still seat a manually added attendee after the flag is turned off', async ({authedPage: page, adminApi, cloudApi, account}) => {
    const accountId = await adminApi.findAccountIdByEmail(account.email);
    await adminApi.setAccountFeatureFlag(accountId, 'seating', true);
    const event = await createSeatedEvent(cloudApi, account.organizerId);
    await adminApi.setAccountFeatureFlag(accountId, 'seating', false);
    const email = uniqueEmail('flag-off-manual');
    await simulateLicence(page, 'CLOUD');

    const attendees = new AttendeePage(page);
    await attendees.goto(event.eventId);
    await page.getByTestId('attendee-create-button').click();
    await page.getByLabel(/^First name/).fill('Manual');
    await page.getByLabel(/^Last name/).fill('Seat');
    await page.getByLabel(/^Email address/).fill(email);
    await page.getByRole('combobox', {name: /^Ticket/}).click();
    await page.getByRole('option', {name: 'Premium Seat'}).click();

    await page.getByTestId('manual-attendee-choose-seat-button').click();
    await page.getByTestId('seat-chooser-map').locator('[data-uid="e2.0.9"]').click();
    await page.getByTestId('seat-chooser-confirm-button').click();
    await page.getByRole('button', {name: 'Create Attendee'}).click();

    await expect(page.getByText('Successfully created attendee')).toBeVisible();
    await expect(attendees.rowByText(email)).toContainText('Stalls · A-10');
  });

  test('a seated event cannot be duplicated into unseated tickets while seating is off', async ({authedPage: page, adminApi, cloudApi, account}) => {
    const accountId = await adminApi.findAccountIdByEmail(account.email);
    await adminApi.setAccountFeatureFlag(accountId, 'seating', true);
    const event = await createSeatedEvent(cloudApi, account.organizerId);
    const {title} = await cloudApi.getEvent(event.eventId);
    await adminApi.setAccountFeatureFlag(accountId, 'seating', false);
    test.skip((await cloudApi.getMe()).feature_flags.seating, NO_SIMULATION);
    await simulateLicence(page, 'CLOUD');

    await page.goto(`/manage/organizer/${account.organizerId}/events`);
    await page.waitForLoadState('networkidle');
    await page.getByPlaceholder('Search by event name...').fill(title);
    await page.waitForURL(/query=/);
    await page.waitForLoadState('networkidle');
    await page.locator('a').filter({has: page.getByRole('heading', {name: title})}).getByRole('button').click();
    await page.getByTestId('event-duplicate-menu-item').click();
    await page.getByRole('dialog').getByLabel(/^Name/).fill(`${title} copy`);
    await page.getByRole('button', {name: 'Duplicate Event'}).click();

    await expect(page.getByText('This event uses a seat map, but reserved seating is not available on your account.', {exact: false})).toBeVisible();
    await expect(page.getByRole('dialog')).toBeVisible();
    expect(page.url()).toMatch(/\/manage\/organizer\//);
  });
});
