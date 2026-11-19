import { readFile } from 'node:fs/promises';
import { test, expect } from '../../fixtures';
import { createCompletedOrder, createCompletedPaidOrder, createLiveEventWithFreeTicket, createLiveEventWithPaidTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { simulateLicence, withLicence } from '../../utils/licence';
import { invitedMember } from '../../utils/team';
import { uniqueEmail, uniqueName, uniqueShort } from '../../utils/unique';
import { askForMobileAtCheckout, buildAudience, createOptedInOrder, marketingStatusOf, UK_MOBILE, waitForContact } from './audience.shared';
import {
  chooseRule,
  closeDialog,
  moveOrderIntoThePast,
  openManageTags,
  readyMadeSegment,
  ruleValue,
  segmentRow,
  tagRow,
  withContactsApi,
} from './contacts.helpers';

const NO_CLOUD = 'This backend is not acting as Hi.Events Cloud (no Cloud licence and X-Hi-Licence-Simulation is ignored), so account feature flags have no effect.';

const expectNoAudienceWriteControls = async (audience: AudiencePage): Promise<void> => {
  await expect(audience.importButton()).toHaveCount(0);
  await expect(audience.addContactButton()).toHaveCount(0);
  await audience.gotoSegments();
  await expect(audience.tab('Segments')).toBeVisible();
  await expect(audience.segmentCreateButton()).toHaveCount(0);
  await audience.gotoCampaigns();
  await expect(audience.tab('Campaigns')).toBeVisible();
  await expect(audience.campaignCreateButton()).toHaveCount(0);
};

test.describe('audience access', () => {
  test('with a lapsed licence the audience is read-only, but contacts can still be unsubscribed and deleted', async ({ freshAccount, publicApi, playwright }) => {
    const me = await withLicence(playwright, freshAccount.token, 'LAPSED', (api) => api.getMe());
    test.skip(me.licence.status !== 'LAPSED', 'This backend ignores X-Hi-Licence-Simulation (APP_ENV is not e2e or testing).');

    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const keep = uniqueEmail('lapsed-keep');
    const remove = uniqueEmail('lapsed-remove');
    const bulk = uniqueEmail('lapsed-bulk');
    await createOptedInOrder(publicApi, event, { email: keep, firstName: 'Lana', lastName: 'Lapsed' });
    await createOptedInOrder(publicApi, event, { email: remove, firstName: 'Del', lastName: 'Lapsed' });
    await buildAudience(api, organizerId);
    const tag = uniqueShort('Kept');
    const segmentName = uniqueShort('Bookers');
    await withContactsApi(playwright, freshAccount, async (contactsApi) => {
      await contactsApi.importContacts(`Email,First Name,Last Name\n${bulk},Bea,Lapsed\n`, { tag, consentAttested: true });
      await contactsApi.createSegment(segmentName, { match: 'all', rules: [{ field: 'events_count', operator: 'gte', value: 1 }] });
    });

    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'LAPSED');
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await expect(page.getByTestId('licence-locked-callout')).toBeVisible();
    await expect(audience.contactRows()).toHaveCount(3);
    await audience.contactsMoreActions().click();
    await expect(audience.exportButton()).toBeVisible();
    await expect(page.getByTestId('audience-resync-button')).toHaveCount(0);
    await page.keyboard.press('Escape');

    const manage = await openManageTags(page, audience);
    await expect(manage.getByRole('textbox', { name: 'New tag name' })).toHaveCount(0);
    await expect(tagRow(manage, tag).getByRole('textbox', { name: 'Tag name', exact: true })).toBeDisabled();
    await expect(tagRow(manage, tag).getByRole('button', { name: 'Delete tag' })).toBeEnabled();
    await closeDialog(manage);

    await audience.rowCheckbox('Bea Lapsed').check();
    await expect(audience.bulkBar().getByRole('button', { name: 'Add tag' })).toBeDisabled();
    await expect(audience.bulkBar().getByRole('button', { name: 'Remove tag' })).toBeDisabled();
    await audience.bulkBar().getByRole('button', { name: 'Add tag' }).hover({ force: true });
    await expect(page.getByRole('tooltip', { name: "Your licence doesn't include Audience, so tags can't be changed" })).toBeVisible();
    await audience.bulkMoreAction('Unsubscribe');
    await audience.bulkConfirmButton().click();
    await expect(audience.contactRow('Bea Lapsed')).toContainText('Unsubscribed');
    await expect(audience.contactRow('Bea Lapsed')).toContainText(tag);

    await audience.filterButton().click();
    await chooseRule(page, audience.filterPanel(), 'Number of events booked');
    await ruleValue(audience.filterPanel()).fill('1');
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(audience.filterPanel().getByRole('button', { name: 'Save as segment' })).toHaveCount(0);

    await audience.gotoSegments();
    await expect(segmentRow(page, segmentName)).toContainText('2 people');
    await segmentRow(page, segmentName).getByRole('button', { name: 'Segment actions' }).click();
    await expect(page.getByRole('menuitem', { name: 'Delete' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Edit' })).toHaveCount(0);
    await page.keyboard.press('Escape');

    await audience.gotoContactsMatching(keep);
    await audience.contactRow('Lana Lapsed').click();
    await expect(audience.noteInput()).toHaveCount(0);
    await expect(audience.tagInput()).toHaveCount(0);
    await audience.moreActions().click();
    await expect(page.getByRole('menuitem', { name: 'Edit name' })).toHaveCount(0);
    await expect(page.getByRole('menuitem', { name: 'Merge with another contact' })).toHaveCount(0);
    await page.getByRole('menuitem', { name: 'Unsubscribe from emails' }).click();
    await audience.confirm('Unsubscribe');
    await expect(audience.marketingStatus()).toContainText('Unsubscribed');
    await expect.poll(() => marketingStatusOf(api, organizerId, keep)).toBe('UNSUBSCRIBED');

    await audience.gotoContactsMatching(remove);
    await audience.contactRow('Del Lapsed').click();
    await audience.openDrawerAction('Delete contact');
    await audience.confirm('Delete contact');
    await expect(audience.contactRows()).toHaveCount(0);
    await expect.poll(async () => (await api.findAudienceContacts(organizerId, remove)).length).toBe(0);

    await audience.gotoContacts();
    await expectNoAudienceWriteControls(audience);
  });

  test('a viewer can read, filter and export the audience but sees no way to change it', async ({ freshAccount, publicApi, mailpit, browser, playwright }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const email = uniqueEmail('viewed');
    await createOptedInOrder(publicApi, event, { email, firstName: 'Vic', lastName: 'Viewed' });
    await buildAudience(api, organizerId);
    const segmentName = uniqueShort('Bookers');
    await withContactsApi(playwright, freshAccount, (contactsApi) =>
      contactsApi.createSegment(segmentName, { match: 'all', rules: [{ field: 'events_count', operator: 'gte', value: 1 }] }));
    const unbuilt = await api.createOrganizer(uniqueName('E2E Unbuilt Org'));

    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'VIEWER' });
    try {
      const audience = new AudiencePage(page, organizerId);
      await audience.gotoContacts();
      await expect(page.getByTestId('view-only-badge')).toBeVisible();
      await expect(audience.contactRow('Vic Viewed')).toBeVisible();
      await audience.contactsMoreActions().click();
      await expect(page.getByRole('menuitem', { name: 'Export this list' })).toBeVisible();
      await expect(page.getByRole('menuitem', { name: 'Manage tags' })).toHaveCount(0);
      await expect(page.getByTestId('audience-resync-button')).toHaveCount(0);
      await page.keyboard.press('Escape');

      await audience.rowCheckbox('Vic Viewed').check();
      await expect(audience.bulkBar()).toContainText('1 selected');
      await expect(audience.bulkBar().getByRole('button', { name: 'Add tag' })).toBeDisabled();
      await expect(audience.bulkBar().getByRole('button', { name: 'Remove tag' })).toBeDisabled();
      await expect(page.getByTestId('audience-bulk-more')).toBeDisabled();
      await audience.bulkBar().getByRole('button', { name: 'Add tag' }).hover({ force: true });
      await expect(page.getByRole('tooltip', { name: "Your role can't edit contacts" })).toBeVisible();
      const [download] = await Promise.all([
        page.waitForEvent('download'),
        audience.bulkBar().getByRole('button', { name: 'Export' }).click(),
      ]);
      expect(await readFile(await download.path(), 'utf8')).toContain(email);
      await audience.bulkBar().getByRole('button', { name: 'Clear selection' }).click();

      await audience.filterButton().click();
      await chooseRule(page, audience.filterPanel(), 'Number of events booked');
      await ruleValue(audience.filterPanel()).fill('2');
      await expect(page.getByText('Nobody matches this search or filter.')).toBeVisible();
      await expect(audience.filterPanel().getByRole('button', { name: 'Save as segment' })).toHaveCount(0);
      await audience.filterPanel().getByRole('button', { name: 'Clear filter' }).click();

      await audience.contactRow('Vic Viewed').click();
      await expect(audience.drawer()).toContainText(email);
      await expect(audience.drawerSection('Consent history')).toContainText('Opted in');
      await expect(audience.moreActions()).toHaveCount(0);
      await expect(audience.noteInput()).toHaveCount(0);
      await expect(audience.tagInput()).toHaveCount(0);
      await expect(audience.drawerSection('Tags')).toContainText('No tags.');
      await page.keyboard.press('Escape');

      await expect(audience.importButton()).toHaveCount(0);
      await expect(audience.addContactButton()).toHaveCount(0);
      await audience.gotoSegments();
      await expect(segmentRow(page, segmentName)).toContainText('1 person');
      await expect(page.getByRole('button', { name: 'Segment actions' })).toHaveCount(0);
      await expect(audience.segmentCreateButton()).toHaveCount(0);
      await segmentRow(page, segmentName).getByRole('link', { name: segmentName, exact: true }).click();
      await expect(audience.contactRow('Vic Viewed')).toBeVisible();

      await audience.gotoCampaigns();
      await expect(audience.tab('Campaigns')).toBeVisible();
      await expect(audience.campaignCreateButton()).toHaveCount(0);
      await audience.gotoAutomations();
      await expect(page.getByText('No automations yet.')).toBeVisible();
      await expect(page.getByTestId('automation-template-EVENT_PUBLISHED')).toHaveCount(0);
      await expect(page.getByTestId('automation-new-button')).toHaveCount(0);

      const other = new AudiencePage(page, unbuilt.id);
      await other.gotoOverview();
      await expect(page.getByText('Someone with edit access needs to turn this on.')).toBeVisible();
      await expect(other.startButton()).toHaveCount(0);
    } finally {
      await page.context().close();
    }
  });

  test('a member who cannot view financials sees no spend on a profile and cannot filter by it', async ({ freshAccount, publicApi, mailpit, browser }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithPaidTicket(api, organizerId);
    const email = uniqueEmail('spender');
    const order = await createCompletedPaidOrder(api, publicApi, event, { buyerEmail: email, buyerFirstName: 'Sy', buyerLastName: 'Spender' });
    const spent = order.totalGross.toFixed(2);
    await buildAudience(api, organizerId);

    const ownerPage = await freshAccount.newAuthedPage();
    const owner = new AudiencePage(ownerPage, organizerId);
    await owner.gotoContactsMatching(email);
    await owner.contactRow('Sy Spender').click();
    await expect(owner.drawerSection('Spend')).toContainText(spent);
    await ownerPage.keyboard.press('Escape');
    await owner.filterButton().click();
    await owner.filterPanel().getByPlaceholder('Choose what to filter by').click();
    await expect(ownerPage.getByRole('option', { name: 'Total spent', exact: true })).toBeVisible();

    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, { role: 'ORGANIZER', permissions: { VIEW_FINANCIALS: false } });
    try {
      const audience = new AudiencePage(page, organizerId);
      await audience.gotoContactsMatching(email);
      await audience.contactRow('Sy Spender').click();
      await expect(audience.drawer().getByText('Placed an order')).toHaveCount(1);
      await expect(audience.drawerSection('Spend')).toHaveCount(0);
      await expect(audience.drawer()).not.toContainText(spent);
      await page.keyboard.press('Escape');
      await audience.filterButton().click();
      await audience.filterPanel().getByPlaceholder('Choose what to filter by').click();
      await expect(page.getByRole('option', { name: 'Used a promo code', exact: true })).toBeVisible();
      await expect(page.getByRole('option', { name: 'Total spent', exact: true })).toHaveCount(0);
    } finally {
      await page.context().close();
    }
  });

  test('the event dashboard shows who is coming to members who manage the organizer, and nothing to event-only members', async ({ freshAccount, publicApi, mailpit, browser }) => {
    const { api, organizerId } = freshAccount;
    const earlier = await createLiveEventWithFreeTicket(api, organizerId);
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const regular = uniqueEmail('regular');
    const before = await createCompletedOrder(publicApi, earlier, { buyerEmail: regular, buyerFirstName: 'Rae', buyerLastName: 'Regular' });
    moveOrderIntoThePast(before.orderShortId, 30);
    await createOptedInOrder(publicApi, event, { email: regular, firstName: 'Rae', lastName: 'Regular' });
    await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail('newcomer'), buyerFirstName: 'Nia', buyerLastName: 'Newcomer' });
    await buildAudience(api, organizerId);

    const ownerPage = await freshAccount.newAuthedPage();
    await ownerPage.goto(`/manage/event/${event.eventId}/dashboard`);
    const section = ownerPage.getByTestId('event-dashboard-audience');
    await expect(section).toContainText('Ticket holders: 1 of 2');
    await expect(section.locator('div').filter({ hasText: /^Booked with you before50%/ }).last()).toBeVisible();
    await expect(section.locator('div').filter({ hasText: /^New to you1First event with you$/ }).last()).toBeVisible();
    await expect(section.locator('div').filter({ hasText: /^Likely to turn up–Needs a past event with check-ins$/ }).last()).toBeVisible();
    await expect(section.locator('div').filter({ hasText: /^Subscribed1Ticket holders you can email$/ }).last()).toBeVisible();
    await expect(section.getByRole('link', { name: /Rae Regular/ })).toContainText('2 events');
    await expect(section).toContainText(earlier.title);
    await section.getByRole('link', { name: /Rae Regular/ }).click();
    await expect(new AudiencePage(ownerPage, organizerId).drawer()).toContainText(regular);

    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      event_ids: [event.eventId],
    });
    try {
      await page.goto(`/manage/event/${event.eventId}/dashboard`);
      await expect(page.getByTestId('event-stat-attendees')).toBeVisible();
      await expect(page.getByTestId('event-dashboard-audience')).toHaveCount(0);

      await page.goto(`/manage/organizer/${organizerId}/audience/contacts`);
      await expect(page.getByRole('heading', { name: "You don't have access to this organizer" })).toBeVisible();
      await expect(page.getByText(regular)).toHaveCount(0);
    } finally {
      await page.context().close();
    }
  });

  test('an event dashboard points to Audience until it has been built', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail('early'), buyerFirstName: 'Eve', buyerLastName: 'Early' });

    const page = await freshAccount.newAuthedPage();
    await page.goto(`/manage/event/${event.eventId}/dashboard`);
    const section = page.getByTestId('event-dashboard-audience');
    await expect(section).toContainText('Set up Audience to see who has been to your events before');
    await expect(section.getByRole('link', { name: 'See full audience' })).toHaveCount(0);
    await section.getByRole('link', { name: 'Open Audience' }).click();

    await expect(page).toHaveURL(new RegExp(`/manage/organizer/${organizerId}/audience$`));
    await expect(page.getByRole('heading', { name: 'See who your audience really is' })).toBeVisible();
    await new AudiencePage(page, organizerId).startButton().click();
    await expect(page.getByText('0 of 1 booked 2+ events')).toBeVisible({ timeout: 30_000 });

    await page.goto(`/manage/event/${event.eventId}/dashboard`);
    await expect(section).toContainText('Ticket holders: 0 of 1');
    await expect(section).toContainText('Everyone here is new to you.');
  });

  test('on Hi.Events Cloud, an account with Audience switched off has no Audience navigation and cannot open it by link', async ({ freshAccount, adminApi, publicApi, playwright }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const email = uniqueEmail('flagged');
    await createCompletedOrder(publicApi, event, { buyerEmail: email, buyerFirstName: 'Fia', buyerLastName: 'Flagged' });
    await buildAudience(api, organizerId);
    const accountId = (await api.getAccount()).id;
    await adminApi.setAccountFeatureFlag(accountId, 'crm', false);
    const me = await withLicence(playwright, freshAccount.token, 'CLOUD', (cloudApi) => cloudApi.getMe());
    test.skip(me.feature_flags.crm !== false, NO_CLOUD);

    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'CLOUD');
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/dashboard`);
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
    await expect(audience.tab('Contacts')).toHaveCount(0);
    await expect(audience.tab('Segments')).toHaveCount(0);
    await expect(audience.tab('Campaigns')).toHaveCount(0);

    await audience.gotoContacts();
    await expect(page.getByText("Audience isn't available on this account")).toBeVisible();
    await expect(page.getByText("Audience hasn't been turned on for this account yet.")).toBeVisible();
    await expect(page.getByTestId('licence-locked-callout')).toHaveCount(0);
    await expect(audience.contactRows()).toHaveCount(0);
    await expect(audience.addContactButton()).toHaveCount(0);
    await page.goto(`/manage/event/${event.eventId}/dashboard`);
    await expect(page.getByTestId('event-stat-attendees')).toBeVisible();
    await expect(page.getByTestId('event-dashboard-audience')).toHaveCount(0);

    await adminApi.setAccountFeatureFlag(accountId, 'crm', true);
    await audience.gotoContacts();
    await expect(audience.tab('Contacts')).toBeVisible();
    await expect(audience.contactRow('Fia Flagged')).toContainText(email);
    await expect(audience.addContactButton()).toBeVisible();
  });

  test('on Hi.Events Cloud, contacts show nothing about text messages until they are switched on for the account', async ({ freshAccount, adminApi, publicApi, playwright }) => {
    const { api, organizerId } = freshAccount;
    const accountId = (await api.getAccount()).id;
    await adminApi.setAccountFeatureFlag(accountId, 'crm_sms', true);
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    await askForMobileAtCheckout(api, event.eventId);
    const email = uniqueEmail('texter');
    await createOptedInOrder(publicApi, event, { email, firstName: 'Tess', lastName: 'Texter', phone: UK_MOBILE, agreesToTexts: true });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, email);
    await adminApi.setAccountFeatureFlag(accountId, 'crm_sms', false);
    const me = await withLicence(playwright, freshAccount.token, 'CLOUD', (cloudApi) => cloudApi.getMe());
    test.skip(me.feature_flags.crm_sms !== false, NO_CLOUD);

    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'CLOUD');
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoSegments();
    await expect(readyMadeSegment(page, 'Subscribers')).toBeVisible();
    await expect(readyMadeSegment(page, 'Text subscribers')).toHaveCount(0);

    await audience.gotoContacts();
    await expect(audience.contactRow('Tess Texter')).toBeVisible();
    await audience.segmentSelect().click();
    await expect(page.getByRole('option', { name: 'Subscribers', exact: true })).toBeVisible();
    await expect(page.getByRole('option', { name: 'Text subscribers' })).toHaveCount(0);
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Columns' }).click();
    await expect(page.getByRole('checkbox', { name: 'Mobile number' })).toBeVisible();
    await expect(page.getByRole('checkbox', { name: 'Text message status' })).toHaveCount(0);
    await page.keyboard.press('Escape');
    await audience.filterButton().click();
    await audience.filterPanel().getByPlaceholder('Choose what to filter by').click();
    await expect(page.getByRole('option', { name: 'Has a mobile number', exact: true })).toBeVisible();
    await expect(page.getByRole('option', { name: 'Text message status' })).toHaveCount(0);
    await page.keyboard.press('Escape');
    await audience.filterButton().click();

    await audience.contactRow('Tess Texter').click();
    await expect(audience.drawerSection('Mobile number')).toContainText('+44 7400 123456');
    await expect(audience.drawerSection('Text message status')).toHaveCount(0);
    await audience.moreActions().click();
    await expect(page.getByRole('menuitem', { name: 'Unsubscribe from emails' })).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Opt out of text messages' })).toHaveCount(0);
    await page.keyboard.press('Escape');

    await adminApi.setAccountFeatureFlag(accountId, 'crm_sms', true);
    await page.reload();
    await expect(audience.drawerSection('Text message status')).toContainText('Subscribed');
    await expect(audience.drawerSection('Text message status')).toContainText('+44 7400 123456');
    await expect(audience.drawerSection('Mobile number')).toHaveCount(0);
    await audience.moreActions().click();
    await expect(page.getByRole('menuitem', { name: 'Opt out of text messages' })).toBeVisible();
    await page.keyboard.press('Escape');
    await page.keyboard.press('Escape');
    await expect(audience.drawer()).toBeHidden();

    await page.getByRole('button', { name: 'Columns' }).click();
    await page.getByRole('checkbox', { name: 'Text message status' }).check();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('columnheader', { name: 'Text message status' })).toBeVisible();
    await audience.segmentSelect().click();
    await page.getByRole('option', { name: 'Text subscribers' }).click();
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Tess Texter')).toBeVisible();

    await audience.gotoSegments();
    await expect(readyMadeSegment(page, 'Text subscribers')).toContainText('Opted in to hear from you by text message');
  });
});
