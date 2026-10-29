import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { invitedMember } from '../../utils/team';
import { uniqueCode, uniqueShort } from '../../utils/unique';

const WRITE_BUTTON = /^\s*(Create|Add|Compose|Invite|Duplicate)\b/;

async function expectReadOnlyPage(page: Page, path: string, heading: RegExp): Promise<void> {
  await page.goto(path);
  await expect(page.getByRole('heading', { name: heading }).first()).toBeVisible();
  await page.waitForLoadState('networkidle');
  await expect(page).not.toHaveURL(/\/auth\/login/);
  await expect(page.getByRole('button', { name: WRITE_BUTTON })).toHaveCount(0);
  await expect(page.getByText('You have view-only access')).toHaveCount(0);
}

async function openFirstRowMenu(page: Page, rowText: string): Promise<void> {
  const row = page.getByRole('row').filter({ hasText: rowText }).first();
  const container = (await row.count()) > 0 ? row : page.getByText(rowText).first().locator('xpath=ancestor::*[.//button][1]');
  await container.getByRole('button').last().click();
}

test.describe('read-only and permission spot checks', () => {
  test('a viewer can browse every event page without seeing create or edit controls', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const order = await createCompletedOrder(publicApi, event);
    const promoCode = uniqueCode('VIEW');
    await freshAccount.api.createPromoCode(event.eventId, { code: promoCode, discount_type: 'NONE' });
    const checkInList = uniqueShort('Door list');
    await freshAccount.api.createCheckInList(event.eventId, { name: checkInList, product_ids: [event.productId] });
    const affiliateCode = uniqueCode('AFF');
    await freshAccount.api.createAffiliate(event.eventId, { name: 'Partner', code: affiliateCode });
    await freshAccount.api.createCapacityAssignment(event.eventId, { name: uniqueShort('Cap'), capacity: 10, status: 'ACTIVE', product_ids: [event.productId] });
    await freshAccount.api.createOrganizerLocation(freshAccount.organizerId, { name: 'Main Hall', structured_address: { city: 'Dublin', country: 'IE' } });
    await freshAccount.api.createQuestion(event.eventId, { title: 'Dietary needs', type: 'SINGLE_LINE_TEXT', belongs_to: 'ORDER', product_ids: [], required: false, is_hidden: false });
    const speaker = await freshAccount.api.createLineupProfile(freshAccount.organizerId, { name: 'Ada Speaker' });
    await freshAccount.api.addLineupEntry(event.eventId, { profile_id: speaker.id, billing_tier: 'HEADLINER' });
    await freshAccount.api.createEventWidget(event.eventId, { name: 'Homepage', type: 'TICKETS', settings: {} });

    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'VIEWER' });
    const base = `/manage/event/${event.eventId}`;

    await expectReadOnlyPage(page, `${base}/dashboard`, /Sam/);
    await expect(page.getByTestId('view-only-badge')).toBeVisible();
    await expect(page.getByRole('link', { name: /^Attendees/ }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: /^(Event Settings|Homepage Designer|Ticket Designer)$/ })).toHaveCount(0);
    await expectReadOnlyPage(page, `${base}/products`, /Tickets|Products/);
    await expectReadOnlyPage(page, `${base}/orders`, /Orders/);
    await expect(page.getByText(order.buyerEmail).first()).toBeVisible();
    await expectReadOnlyPage(page, `${base}/attendees`, /Attendees/);
    await expectReadOnlyPage(page, `${base}/questions`, /Questions/);
    await expect(page.getByText('Dietary needs').first()).toBeVisible();
    await expectReadOnlyPage(page, `${base}/promo-codes`, /Promo Codes/);
    await expect(page.getByText(promoCode).first()).toBeVisible();
    await expectReadOnlyPage(page, `${base}/check-in`, /Check-In/);
    await expect(page.getByText(checkInList).first()).toBeVisible();
    await expectReadOnlyPage(page, `${base}/capacity-assignments`, /Capacity/);
    await expectReadOnlyPage(page, `${base}/affiliates`, /Affiliates/);
    await expect(page.getByText(affiliateCode).first()).toBeVisible();
    await expectReadOnlyPage(page, `${base}/messages`, /Messages/);
    await expectReadOnlyPage(page, `${base}/widget`, /Embed Widget/);
    await expect(page.getByTestId('widget-copy-code-button')).toBeVisible();
    await expect(page.getByTestId('widget-save-button')).toHaveCount(0);
    await page.getByTestId('widget-switcher').click();
    await page.getByRole('option', { name: 'Homepage' }).click();
    await expect(page.getByTestId('widget-stats')).toBeVisible();
    await expect(page.getByTestId('widget-options-button')).toHaveCount(0);
    await page.getByTestId('widget-group-promo').click();
    await expect(page.getByTestId('widget-hide-promo-code-switch')).toBeDisabled();
    await expect(page.getByTestId('widget-copy-code-button')).toBeVisible();
    await expectReadOnlyPage(page, `${base}/lineup`, /Lineup & Schedule/);
    await expect(page.getByTestId('lineup-people-list')).toContainText('Ada Speaker');
    await expect(page.getByTestId('lineup-quick-add-input')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Drag to reorder' })).toHaveCount(0);
    await expect(page.getByTestId('lineup-import-button')).toHaveCount(0);
    await page.getByTestId('lineup-export-button').click();
    const [download] = await Promise.all([page.waitForEvent('download'), page.getByTestId('lineup-export-xlsx').click()]);
    expect(download.suggestedFilename()).toMatch(/-run-sheet\.xlsx$/);
    await page.goto(`${base}/lineup/print`);
    await expect(page.getByText('Run sheet', { exact: true })).toBeVisible();
    await expectReadOnlyPage(page, `/manage/organizer/${freshAccount.organizerId}/people`, /People/);
    await expect(page.getByTestId('people-create-button')).toHaveCount(0);

    await page.goto(`${base}/promo-codes`);
    await openFirstRowMenu(page, promoCode);
    await expect(page.getByRole('menuitem', { name: 'Copy URL' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: /Edit Code|Delete code/ })).toHaveCount(0);

    await page.goto(`${base}/orders`);
    await openFirstRowMenu(page, order.buyerEmail);
    await expect(page.getByRole('menuitem', { name: /Manage order/ })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: /Cancel order|Refund order|Edit|Message buyer/ })).toHaveCount(0);

    await expectReadOnlyPage(page, `/manage/organizer/${freshAccount.organizerId}/events`, /Events/);
    await expectReadOnlyPage(page, `/manage/organizer/${freshAccount.organizerId}/locations`, /Locations/);
    await expect(page.getByText('Main Hall').first()).toBeVisible();
    await expect(page.getByRole('row').filter({ hasText: 'Main Hall' }).getByRole('button')).toHaveCount(0);
    await expectReadOnlyPage(page, '/manage/events', /Events/);
  });

  test('an organizer without financials sees no money, reports, webhooks or refunds', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    await createCompletedOrder(publicApi, event);
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      permissions: { VIEW_FINANCIALS: false },
    });

    await page.goto(`/manage/event/${event.eventId}/dashboard`);
    await expect(page.getByTestId('event-stat-attendees')).toBeVisible();
    await expect(page.getByTestId('event-stat-gross_sales')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Reports' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Webhooks' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Team Access' })).toHaveCount(0);

    await page.goto(`/manage/event/${event.eventId}/orders`);
    await expect(page.getByText('Test Buyer')).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Amount' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Export' })).toHaveCount(0);
    await openFirstRowMenu(page, 'Test Buyer');
    await expect(page.getByRole('menuitem', { name: /Refund order|Download invoice/ })).toHaveCount(0);
    await expect(page.getByRole('menuitem', { name: /Cancel order/ })).toBeVisible();

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/reports`);
    await expect(page.getByText('Check-In Summary').first()).toBeVisible();
    await expect(page.getByText('Revenue Summary')).toHaveCount(0);
  });

  test('a non-admin member cannot reach team or account management', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'ORGANIZER' });

    await page.goto('/settings/team');
    await expect(page).toHaveURL(/\/settings\/profile/);
    await expect(page.getByRole('link', { name: 'Team', exact: true })).toHaveCount(0);
    await expect(page.getByTestId('team-invite-button')).toHaveCount(0);

    await page.goto(`/manage/event/${event.eventId}/dashboard`);
    await expect(page.getByRole('link', { name: 'Team Access' })).toHaveCount(0);
    await expect(page.getByText('Team', { exact: true })).toHaveCount(0);
  });

  test('an event-only member is kept out of organizer and account pages', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const event = await createLiveEventWithFreeTicket(freshAccount.api, freshAccount.organizerId);
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      event_ids: [event.eventId],
    });

    await page.goto(`/manage/event/${event.eventId}/dashboard`);
    await expect(page.getByRole('link', { name: 'Organizer Dashboard' })).toHaveCount(0);

    await page.goto(`/manage/event/${event.eventId}/lineup`);
    await expect(page.getByTestId('lineup-export-button')).toBeVisible();
    await expect(page.getByTestId('lineup-import-button')).toHaveCount(0);

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/dashboard`);
    await expect(page.getByRole('heading', { name: "You don't have access to this organizer" })).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/manage/organizer/${freshAccount.organizerId}/dashboard`));

    await page.goto('/account/settings');
    await expect(page).toHaveURL(/\/settings\/profile/);
    await expect(page.getByRole('link', { name: 'General', exact: true })).toHaveCount(0);
    await expect(page).not.toHaveURL(/\/auth\/login/);
  });
});
