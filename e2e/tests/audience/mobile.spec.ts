import { test, expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, withAudienceApi } from './audience.shared';
import { chooseRule, expectNoSidewaysScroll, PHONE_VIEWPORT, ruleValue, segmentRow } from './contacts.helpers';
import { withMessagingApi } from './messaging.shared';

test.describe('audience on a phone', () => {
  test('the overview, segments, campaigns, automations and forms fit a phone screen', async ({ freshAccount, publicApi, playwright }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await createOptedInOrder(publicApi, event, { email: uniqueEmail('pocket'), firstName: 'Polly', lastName: 'Pocket' });
    await buildAudience(api, organizerId);
    const formName = uniqueShort('Pocket news');
    await api.updateOrganizerStatus(organizerId, 'LIVE');
    await withMessagingApi(playwright, freshAccount, (messaging) => messaging.createSignupForm({ name: formName, title: 'Hear about new shows' }));

    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize(PHONE_VIEWPORT);
    const audience = new AudiencePage(page, organizerId);

    await audience.gotoOverview();
    await expect(page.getByRole('heading', { name: 'Audience', level: 1 })).toBeVisible();
    await expect(page.getByText('Came back')).toBeVisible();
    await expect(page.getByText('100% of your contacts')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'When people buy' })).toBeVisible();
    await expectNoSidewaysScroll(page);

    await audience.gotoSegments();
    await expect(page.getByRole('link', { name: /Repeat buyers/ })).toBeVisible();
    await expectNoSidewaysScroll(page);

    await audience.gotoAutomations();
    await expect(page.getByTestId('automation-template-WELCOME')).toBeVisible();
    await expectNoSidewaysScroll(page);

    await audience.gotoForms();
    await expect(page.getByTestId('signup-form-row').filter({ hasText: formName })).toBeVisible();
    await expectNoSidewaysScroll(page);

    await audience.gotoCampaigns();
    await expect(audience.campaignCreateButton()).toBeVisible();
    await expectNoSidewaysScroll(page);
  });

  test('the contact list, a profile, the filter and the bulk bar work on a phone', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const first = await createLiveEventWithFreeTicket(api, organizerId);
    const second = await createLiveEventWithFreeTicket(api, organizerId);
    const regular = uniqueEmail('zed');
    const once = uniqueEmail('amy');
    await createOptedInOrder(publicApi, first, { email: regular, firstName: 'Zed', lastName: 'Pocket' });
    await createCompletedOrder(publicApi, second, { buyerEmail: regular, buyerFirstName: 'Zed', buyerLastName: 'Pocket' });
    await createOptedInOrder(publicApi, first, { email: once, firstName: 'Amy', lastName: 'Pocket' });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize(PHONE_VIEWPORT);
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();

    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(page.getByRole('table')).toHaveCount(0);
    await expect(audience.contactRow('Zed Pocket')).toContainText('2 events');
    await expect(audience.contactRow('Zed Pocket')).toContainText(regular);
    await expect(audience.contactRow('Amy Pocket')).toContainText('1 event');
    await expect(audience.contactRow('Amy Pocket')).toContainText('Subscribed');
    await expectNoSidewaysScroll(page);

    await page.getByRole('button', { name: 'Last active' }).click();
    await page.getByRole('menuitem', { name: 'Name' }).click();
    await expect(audience.contactRows().first()).toContainText('Amy Pocket');
    await page.getByRole('button', { name: 'Name' }).click();
    await page.getByRole('menuitem', { name: 'Name' }).click();
    await expect(audience.contactRows().first()).toContainText('Zed Pocket');

    await audience.contactRow('Zed Pocket').getByRole('button', { name: 'Zed Pocket' }).click();
    await expect(audience.drawer()).toContainText(regular);
    await expect(audience.drawer().getByText('Placed an order')).toHaveCount(2);
    await expect(audience.marketingStatus()).toContainText('Subscribed');
    await expectNoSidewaysScroll(page);
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();

    await audience.filterButton().click();
    const panel = audience.filterPanel();
    await chooseRule(page, panel, 'Number of events booked');
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await ruleValue(panel).fill('2');
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Zed Pocket')).toBeVisible();
    await expectNoSidewaysScroll(page);
    await panel.getByRole('button', { name: 'Clear filter' }).click();
    await expect(audience.contactTotal()).toHaveText('2 contacts');

    await audience.rowCheckbox('Amy Pocket').check();
    await expect(audience.bulkBar()).toContainText('1 selected');
    await expect(audience.bulkBar()).toBeInViewport({ ratio: 1 });
    await expectNoSidewaysScroll(page);
    const tag = uniqueShort('Pals');
    await audience.bulkTag('Add tag', tag);
    await expect(audience.contactRow('Amy Pocket')).toContainText(tag);
    await expect(audience.contactRow('Zed Pocket')).not.toContainText(tag);

    await audience.selectPageCheckbox().check();
    await expect(audience.bulkBar()).toContainText('2 selected');
    await audience.bulkMoreAction('Unsubscribe');
    const confirm = page.getByRole('dialog', { name: 'Unsubscribe 2 contacts from marketing emails?' });
    await expect(confirm).toBeVisible();
    await expectNoSidewaysScroll(page);
    await audience.bulkConfirmButton().click();
    await expect(audience.contactRow('Amy Pocket')).toContainText('Unsubscribed');
    await expect(audience.contactRow('Zed Pocket')).toContainText('Unsubscribed');
  });

  test('a segment is built and the suppressed list is filtered by reason on a phone', async ({ freshAccount, publicApi, playwright }) => {
    const { api, organizerId } = freshAccount;
    const first = await createLiveEventWithFreeTicket(api, organizerId);
    const second = await createLiveEventWithFreeTicket(api, organizerId);
    const regular = uniqueEmail('regular');
    await createCompletedOrder(publicApi, first, { buyerEmail: regular, buyerFirstName: 'Rae', buyerLastName: 'Pocket' });
    await createCompletedOrder(publicApi, second, { buyerEmail: regular, buyerFirstName: 'Rae', buyerLastName: 'Pocket' });
    await createCompletedOrder(publicApi, first, { buyerEmail: uniqueEmail('once'), buyerFirstName: 'Ola', buyerLastName: 'Pocket' });
    await buildAudience(api, organizerId);
    const bounced = uniqueEmail('bounced');
    const unsubscribed = uniqueEmail('unsubscribed');
    await withAudienceApi(playwright, freshAccount, (audienceApi) =>
      audienceApi.importSuppressions('old-tool.csv', `Email,Reason\n${bounced},bounced\n${unsubscribed},\n`));

    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize(PHONE_VIEWPORT);
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoSegments();
    const name = uniqueShort('Pocket regulars');
    await audience.segmentCreateButton().click();
    const modal = page.getByRole('dialog', { name: 'New segment' });
    await page.getByTestId('segment-name').fill(name);
    await chooseRule(page, modal, 'Number of events booked');
    await ruleValue(modal).fill('2');
    await expect(page.getByTestId('segment-live-count')).toContainText('1 person matches');
    await expectNoSidewaysScroll(page);
    await page.getByTestId('segment-save').click();
    await expect(modal).toBeHidden();
    await expect(segmentRow(page, name)).toContainText('1 person');
    await expectNoSidewaysScroll(page);

    await segmentRow(page, name).getByRole('link', { name, exact: true }).click();
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Rae Pocket')).toContainText('2 events');

    await audience.gotoSuppressed();
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(page.getByRole('radiogroup')).toHaveCount(0);
    await audience.suppressionCounts().getByLabel('Reason').click();
    await page.getByRole('option', { name: 'Bounced · 1' }).click();
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow(bounced)).toContainText('Imported from old-tool.csv');
    await expect(audience.contactRow(unsubscribed)).toHaveCount(0);
    await expectNoSidewaysScroll(page);
  });
});
