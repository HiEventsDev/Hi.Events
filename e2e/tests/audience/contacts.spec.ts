import { readFile } from 'node:fs/promises';
import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createCompletedOrder, createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, marketingStatusOf } from './audience.shared';
import { chooseRule, chooseRuleOption, importFamily, ruleValue, withContactsApi } from './contacts.helpers';

test.describe('audience contacts', () => {
  test('a contact can be noted, tagged, renamed and unsubscribed from their profile', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const email = uniqueEmail('profile');
    await createOptedInOrder(publicApi, event, { email, firstName: 'Priya', lastName: 'Profile' });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(email);
    await audience.contactRow('Priya Profile').click();

    const consent = audience.drawerSection('Consent history');
    await expect(consent).toContainText('Opted in');
    await expect(consent).toContainText('Ticked the marketing box at checkout');
    await expect(audience.marketingStatus()).toContainText('Subscribed');

    const note = 'Prefers an aisle seat';
    await audience.noteInput().fill(note);
    await audience.drawer().getByRole('button', { name: 'Save note' }).click();
    const notes = audience.drawerSection('Notes');
    await expect(notes.getByText(note)).toBeVisible();
    await expect(audience.noteInput()).toHaveValue('');
    await notes.getByRole('button', { name: 'Delete note' }).click();
    await audience.confirm('Delete note');
    await expect(notes.getByText(note)).toHaveCount(0);

    const tag = uniqueShort('VIP');
    await audience.tagInput().fill(tag);
    await audience.tagInput().press('Enter');
    await expect(audience.drawerSection('Tags')).toContainText(tag);
    await expect(audience.contactRow(email)).toContainText(tag);

    await audience.openDrawerAction('Edit name');
    const editName = page.getByRole('dialog', { name: 'Edit name' });
    await editName.getByLabel('First name').fill('Pria');
    await editName.getByLabel('Last name').fill('Renamed');
    await editName.getByRole('button', { name: 'Save' }).click();
    await expect(editName).toBeHidden();
    await expect(audience.drawer()).toContainText('Pria Renamed');
    await expect(audience.contactRow(email)).toContainText('Pria Renamed');

    await audience.openDrawerAction('Unsubscribe from emails');
    await audience.confirm('Unsubscribe');
    await expect(audience.marketingStatus()).toContainText('Unsubscribed');
    await expect(consent).toContainText('Opted out');
    await expect(consent).toContainText('By E2E Organizer');
    await expect(audience.moreActions()).toBeVisible();
    await audience.moreActions().click();
    await expect(page.getByRole('menuitem', { name: 'Unsubscribe from emails' })).toHaveCount(0);
    await page.keyboard.press('Escape');

    await page.keyboard.press('Escape');
    await expect(audience.contactRow(email)).toContainText('Unsubscribed');
    await expect.poll(() => marketingStatusOf(api, organizerId, email)).toBe('UNSUBSCRIBED');
  });

  test('making an address that never opted in the primary one asks first and stops campaigns to them', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const subscribed = uniqueEmail('opted-in');
    const silent = uniqueEmail('never-opted');
    await createOptedInOrder(publicApi, event, { email: subscribed, firstName: 'Pat', lastName: 'Primary' });
    await createCompletedOrder(publicApi, event, { buyerEmail: silent, buyerFirstName: 'Pat', buyerLastName: 'Primary' });
    await buildAudience(api, organizerId);
    const [target] = await api.findAudienceContacts(organizerId, subscribed);
    const [source] = await api.findAudienceContacts(organizerId, silent);
    await api.mergeAudienceContacts(organizerId, target.id, source.id);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(subscribed);
    await audience.contactRow('Pat Primary').click();
    await expect(audience.marketingStatus()).toContainText('Subscribed');

    const addresses = audience.drawerSection('Email addresses');
    await addresses.getByRole('button', { name: 'Make primary' }).click();
    const confirm = page.getByRole('dialog', { name: `Make ${silent} the primary address?` });
    await expect(confirm).toContainText('this person will stop receiving campaigns');
    await confirm.getByRole('button', { name: 'Make primary' }).click();

    await expect(audience.marketingStatus()).toContainText('Not subscribed');
    await expect(audience.primaryMark(silent)).toBeVisible();
    await expect(audience.primaryMark(subscribed)).toHaveCount(0);
    await expect.poll(() => marketingStatusOf(api, organizerId, silent)).toBe('NOT_SUBSCRIBED');

    await addresses.getByRole('button', { name: 'Make primary' }).click();
    const back = page.getByRole('dialog', { name: `Make ${subscribed} the primary address?` });
    await expect(back).toContainText('this person will start receiving campaigns');
    await back.getByRole('button', { name: 'Make primary' }).click();
    await expect(audience.marketingStatus()).toContainText('Subscribed');
    await expect(audience.primaryMark(subscribed)).toBeVisible();
    await expect(audience.primaryMark(silent)).toHaveCount(0);
    await expect.poll(() => marketingStatusOf(api, organizerId, subscribed)).toBe('SUBSCRIBED');
  });

  test('a contact can be added by hand, and a deleted one only comes back after confirming', async ({ freshAccount }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const email = uniqueEmail('manual');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await audience.addContact(email, 'Hana', 'Hand');
    await expect(audience.drawer()).toContainText('Hana Hand');
    await expect(audience.marketingStatus()).toContainText('Not subscribed');

    await audience.openDrawerAction('Delete contact');
    await audience.confirm('Delete contact');
    await expect(audience.contactRow(email)).toHaveCount(0);

    await audience.addContact(email, 'Hana', 'Hand');
    const restore = page.getByRole('dialog', { name: 'Restore this person?' });
    await expect(restore).toBeVisible();
    await expect(audience.contactRow(email)).toHaveCount(0);
    await restore.getByRole('button', { name: 'Restore contact' }).click();

    await expect(audience.drawer()).toContainText('Hana Hand');
    await page.keyboard.press('Escape');
    await expect(audience.contactRow(email)).toContainText('Hana Hand');
    await expect.poll(async () => (await api.findAudienceContacts(organizerId, email)).length).toBe(1);
  });

  test('exporting downloads the contacts the list is showing', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const wanted = uniqueEmail('export-me');
    const other = uniqueEmail('leave-me');
    await createCompletedOrder(publicApi, event, { buyerEmail: wanted, buyerFirstName: 'Ezra', buyerLastName: 'Export' });
    await createCompletedOrder(publicApi, event, { buyerEmail: other, buyerFirstName: 'Lou', buyerLastName: 'Left' });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await expect(audience.contactRows()).toHaveCount(2);

    const everyone = await readDownload(page, audience);
    expect(everyone.name).toBe('contacts.csv');
    expect(everyone.body).toContain(wanted);
    expect(everyone.body).toContain(other);

    await audience.search(wanted);
    await expect(audience.contactRows()).toHaveCount(1);
    const searched = await readDownload(page, audience);
    expect(searched.body).toContain(wanted);
    expect(searched.body).toContain('Ezra');
    expect(searched.body).not.toContain(other);

    await audience.search('');
    await expect(audience.contactRows()).toHaveCount(2);
    await audience.rowCheckbox('Lou Left').check();
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      audience.bulkBar().getByRole('button', { name: 'Export' }).click(),
    ]);
    const selected = await readFile(await download.path(), 'utf8');
    expect(selected).toContain(other);
    expect(selected).not.toContain(wanted);
  });

  test('the inline filter narrows the list, follows browser history and saves as a segment', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const events = [
      await createLiveEventWithFreeTicket(api, organizerId),
      await createLiveEventWithFreeTicket(api, organizerId),
      await createLiveEventWithFreeTicket(api, organizerId),
    ];
    const thrice = uniqueEmail('thrice');
    const twice = uniqueEmail('twice');
    for (const event of events) {
      await createCompletedOrder(publicApi, event, { buyerEmail: thrice, buyerFirstName: 'Cy', buyerLastName: 'Thrice' });
    }
    for (const event of events.slice(0, 2)) {
      await createCompletedOrder(publicApi, event, { buyerEmail: twice, buyerFirstName: 'Ada', buyerLastName: 'Twice' });
    }
    await createCompletedOrder(publicApi, events[0], { buyerEmail: uniqueEmail('once'), buyerFirstName: 'Ben', buyerLastName: 'Once' });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await expect(audience.contactTotal()).toHaveText('3 contacts');

    await audience.search('Thrice');
    await audience.contactRow('Cy Thrice').click();
    await expect(page).toHaveURL(/query=Thrice/);
    await expect(page).toHaveURL(/contact=\d+/);
    await expect(audience.drawer()).toBeVisible();
    await page.keyboard.press('Escape');
    await audience.gotoContacts();
    await expect(audience.contactTotal()).toHaveText('3 contacts');

    await audience.filterButton().click();
    const panel = audience.filterPanel();
    await expect(panel.getByRole('button', { name: 'Add a rule' })).toBeDisabled();
    await expect(panel.getByRole('button', { name: 'Save as segment' })).toHaveCount(0);
    await chooseRule(page, panel, 'Number of events booked');
    await expect(panel).toContainText('Not applied yet. Finish this rule to use it.');
    await expect(audience.filterButton()).toHaveText('Filter');
    await expect(audience.contactTotal()).toHaveText('3 contacts');
    await expect(page).not.toHaveURL(/filter=/);

    const value = ruleValue(panel);
    await value.fill('3');
    await expect(page).toHaveURL(/filter=/);
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Cy Thrice')).toBeVisible();
    await expect(audience.filterButton()).toHaveText('Filter · 1 rule');
    await expect(panel).not.toContainText('Not applied yet.');

    await value.fill('2');
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(audience.contactRow('Ada Twice')).toBeVisible();

    await page.goBack();
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(value).toHaveValue('3');

    await panel.getByRole('button', { name: 'Add a rule' }).click();
    await chooseRule(page, panel, 'Tag');
    await expect(panel).toContainText('Not applied yet. Finish this rule to use it.');
    await expect(audience.filterButton()).toHaveText('Filter · 1 rule');
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await panel.getByRole('button', { name: 'Remove rule' }).last().click();
    await expect(panel).not.toContainText('Not applied yet.');

    const name = uniqueShort('Superfans');
    await audience.filterPanel().getByRole('button', { name: 'Save as segment' }).click();
    await page.getByTestId('segment-name').fill(name);
    await page.getByTestId('segment-save').click();

    await expect(page).toHaveURL(/segment=saved/);
    await expect(audience.filterPanel()).toHaveCount(0);
    await expect(audience.segmentSelect()).toHaveValue(name);
    await expect(audience.contactTotal()).toHaveText('1 contact');

    await audience.gotoSegments();
    await expect(page.getByRole('link', { name })).toBeVisible();
  });

  test('the list is sorted from its headings and paged, and a filter can be cleared', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const family = uniqueShort('Sorted');
    await importFamily(playwright, freshAccount, family, 27);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(family);
    await expect(audience.contactTotal()).toHaveText('27 contacts');

    await page.getByRole('columnheader').getByRole('button', { name: 'Contact' }).click();
    await expect(audience.contactRows().first()).toContainText(`Member001 ${family}`);
    await expect(page).toHaveURL(/sortBy=first_name/);
    await page.getByRole('columnheader').getByRole('button', { name: 'Contact' }).click();
    await expect(audience.contactRows().first()).toContainText(`Member027 ${family}`);
    await expect(page.getByRole('columnheader').getByRole('button', { name: 'Contact' })).toContainText('Sorted descending');

    await page.getByRole('button', { name: '2', exact: true }).click();
    await expect(audience.contactRows()).toHaveCount(2);
    await expect(audience.contactRows().last()).toContainText(`Member001 ${family}`);

    await audience.filterButton().click();
    const panel = audience.filterPanel();
    await chooseRule(page, panel, 'How they were added');
    await chooseRuleOption(page, panel, 'Added manually');
    await expect(page.getByText('Nobody matches this search or filter.')).toBeVisible();
    await panel.getByRole('button', { name: 'Clear filter' }).click();
    await expect(audience.filterPanel()).toHaveCount(0);
    await expect(audience.contactTotal()).toHaveText('27 contacts');
  });

  test('a link to a segment or contact that no longer exists says so', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const lone = uniqueEmail('lone');
    const segment = await withContactsApi(playwright, freshAccount, async (contactsApi) => {
      await contactsApi.importContacts(`Email,First Name,Last Name\n${lone},Lone,Contact\n`);
      const created = await contactsApi.createSegment(uniqueShort('Gone'), { match: 'all', rules: [{ field: 'events_count', operator: 'gte', value: 1 }] });
      await contactsApi.deleteSegment(created.id);
      return created;
    });

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts(`?segment=saved:${segment.id}`);
    await expect(page.getByText('This segment has been deleted. Pick another one to see its contacts.')).toBeVisible();
    await expect(audience.contactRows()).toHaveCount(0);
    await audience.segmentSelect().click();
    await page.getByRole('option', { name: 'All contacts' }).click();
    await expect(audience.contactRow(lone)).toBeVisible();

    await audience.gotoContacts('?contact=999999999');
    const missing = page.getByRole('dialog').filter({ hasText: 'Contact not found' });
    await expect(missing).toContainText('This contact no longer exists. They may have been merged or deleted.');
    await expect(audience.contactRow(lone)).toBeVisible();
  });
});

async function readDownload(page: Page, audience: AudiencePage): Promise<{ name: string; body: string }> {
  await audience.contactsMoreActions().click();
  const [download] = await Promise.all([page.waitForEvent('download'), audience.exportButton().click()]);
  const path = await download.path();
  return { name: download.suggestedFilename(), body: await readFile(path, 'utf8') };
}
