import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, marketingStatusOf, withAudienceApi } from './audience.shared';
import { familyCsv, importFamily, withContactsApi } from './contacts.helpers';

test.describe('audience bulk actions and saved views', () => {
  test('selected contacts are tagged in bulk and the change can be undone', { tag: '@smoke' }, async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const family = uniqueShort('Bulkson');
    for (const first of ['Ada', 'Ben', 'Cleo']) {
      await createOptedInOrder(publicApi, event, { email: uniqueEmail(first.toLowerCase()), firstName: first, lastName: family });
    }
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(family);
    await expect(audience.contactRows()).toHaveCount(3);

    await audience.rowCheckbox(`Ada ${family}`).check();
    await audience.rowCheckbox(`Ben ${family}`).check();
    await expect(audience.bulkBar()).toContainText('2 selected');

    const tag = uniqueShort('Press');
    await audience.bulkTag('Add tag', tag);
    await expect(page.getByText(`Tagged 2 contacts with ${tag}`)).toBeVisible();
    await expect(audience.contactRow(`Ada ${family}`)).toContainText(tag);
    await expect(audience.contactRow(`Ben ${family}`)).toContainText(tag);
    await expect(audience.contactRow(`Cleo ${family}`)).not.toContainText(tag);
    await expect(audience.bulkBar()).toBeHidden();

    await page.getByRole('button', { name: 'Undo' }).click();
    await expect(audience.contactRow(`Ada ${family}`)).not.toContainText(tag);
    await expect(audience.contactRow(`Ben ${family}`)).not.toContainText(tag);
  });

  test('tagging people who already have the tag changes nothing, and the selection can be cleared', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const family = uniqueShort('Tagged');
    const tag = uniqueShort('Friends');
    await withContactsApi(playwright, freshAccount, (contactsApi) => contactsApi.importContacts(familyCsv(family, 2), { tag }));

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(family);
    await audience.selectPageCheckbox().check();
    await expect(audience.bulkBar()).toContainText('2 selected');
    await audience.bulkTag('Add tag', tag);
    await expect(page.getByText(`Everyone selected already has the ${tag} tag`)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Undo' })).toHaveCount(0);
    await expect(audience.contactRow(`Member001 ${family}`)).toContainText(tag);

    await audience.rowCheckbox(`Member001 ${family}`).check();
    await expect(audience.bulkBar()).toContainText('1 selected');
    await audience.bulkBar().getByRole('button', { name: 'Clear selection' }).click();
    await expect(audience.bulkBar()).toBeHidden();
    await expect(audience.rowCheckbox(`Member001 ${family}`)).not.toBeChecked();
  });

  test('selected contacts are unsubscribed and deleted in bulk after confirming', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const family = uniqueShort('Purge');
    const emails = ['dora', 'eli'].map(name => uniqueEmail(name));
    await createOptedInOrder(publicApi, event, { email: emails[0], firstName: 'Dora', lastName: family });
    await createOptedInOrder(publicApi, event, { email: emails[1], firstName: 'Eli', lastName: family });
    await buildAudience(api, organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(family);
    await expect(audience.contactRows()).toHaveCount(2);

    await audience.selectPageCheckbox().check();
    await expect(audience.contactRow(`Dora ${family}`)).toContainText('Subscribed');
    await audience.bulkMoreAction('Unsubscribe');
    await expect(page.getByRole('dialog', { name: 'Unsubscribe 2 contacts?' })).toContainText("Contacts who haven't opted in or whose address bounced are marked as unsubscribed too. Your team can't undo this. They're only subscribed again if they opt in themselves, at checkout or through a signup form.");
    await audience.bulkConfirmButton().click();
    await expect(audience.contactRow(`Dora ${family}`)).toContainText('Unsubscribed');
    await expect(audience.contactRow(`Eli ${family}`)).toContainText('Unsubscribed');
    await expect(audience.bulkBar()).toBeHidden();
    await expect.poll(() => marketingStatusOf(api, organizerId, emails[0])).toBe('UNSUBSCRIBED');
    await expect.poll(() => marketingStatusOf(api, organizerId, emails[1])).toBe('UNSUBSCRIBED');

    await audience.rowCheckbox(`Dora ${family}`).check();
    await audience.bulkMoreAction('Delete');
    await expect(page.getByRole('dialog', { name: 'Delete 1 contact?' })).toContainText("Their orders and tickets aren't affected");
    await audience.bulkConfirmButton().click();
    await expect(audience.contactRows()).toHaveCount(1);
    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow(`Eli ${family}`)).toBeVisible();
  });

  test('every matching contact is selected across pages and deleted after typing the count', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const family = uniqueShort('Mass');
    await importFamily(playwright, freshAccount, family, 26);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(family);
    await expect(audience.contactTotal()).toHaveText('26 contacts');
    await expect(audience.contactRows()).toHaveCount(25);

    await audience.selectPageCheckbox().check();
    await expect(page.getByText('All 25 contacts on this page are selected.')).toBeVisible();
    await expect(audience.bulkBar()).toContainText('25 selected');
    await audience.selectAllMatchingButton().click();
    await expect(page.getByText('All 26 contacts are selected.')).toBeVisible();
    await expect(audience.bulkBar()).toContainText('26 selected');

    await audience.bulkMoreAction('Delete');
    const confirm = page.getByRole('dialog', { name: 'Delete 26 contacts?' });
    await expect(audience.bulkConfirmButton()).toBeDisabled();
    await confirm.getByLabel('Type 26 to confirm').fill('25');
    await expect(audience.bulkConfirmButton()).toBeDisabled();
    await confirm.getByLabel('Type 26 to confirm').fill('26');
    await audience.bulkConfirmButton().click();

    await expect(page.getByText('Nobody matches this search or filter.')).toBeVisible();
    await expect(audience.contactRows()).toHaveCount(0);
    await expect.poll(async () => (await api.findAudienceContacts(organizerId, family)).length).toBe(0);
  });

  test('a delete asks again with the new count when the selection grows before it is confirmed', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const family = uniqueShort('Grow');
    await importFamily(playwright, freshAccount, family, 26);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(family);
    await expect(audience.contactTotal()).toHaveText('26 contacts');
    await audience.selectPageCheckbox().check();
    await audience.selectAllMatchingButton().click();
    await audience.bulkMoreAction('Delete');
    await page.getByRole('dialog', { name: 'Delete 26 contacts?' }).getByLabel('Type 26 to confirm').fill('26');

    await withAudienceApi(playwright, freshAccount, (audienceApi) =>
      audienceApi.createContact({ email: uniqueEmail('late'), first_name: 'Late', last_name: family }));
    await audience.bulkConfirmButton().click();

    const retry = page.getByRole('dialog', { name: 'Delete 27 contacts?' });
    await expect(retry).toContainText('This selection has changed. It now has 27 contacts.');
    await expect(audience.bulkConfirmButton()).toBeDisabled();
    await retry.getByLabel('Type 27 to confirm').fill('27');
    await audience.bulkConfirmButton().click();

    await expect(page.getByText('Nobody matches this search or filter.')).toBeVisible();
    await expect.poll(async () => (await api.findAudienceContacts(organizerId, family)).length).toBe(0);
  });

  test('an unsubscribe asks again with the new count when the selection grows, and then stops campaigns to everyone selected', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const family = uniqueShort('Swell');
    await withContactsApi(playwright, freshAccount, (contactsApi) => contactsApi.importContacts(familyCsv(family, 26), { consentAttested: true }));

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(family);
    await expect(audience.contactTotal()).toHaveText('26 contacts');
    await audience.selectPageCheckbox().check();
    await audience.selectAllMatchingButton().click();
    await audience.bulkMoreAction('Unsubscribe');
    await page.getByRole('dialog', { name: 'Unsubscribe 26 contacts?' }).getByLabel('Type 26 to confirm').fill('26');

    await withAudienceApi(playwright, freshAccount, (audienceApi) =>
      audienceApi.createContact({ email: uniqueEmail('late'), first_name: 'Late', last_name: family }));
    await audience.bulkConfirmButton().click();

    const retry = page.getByRole('dialog', { name: 'Unsubscribe 27 contacts?' });
    await expect(retry).toContainText('This selection has changed. It now has 27 contacts.');
    await expect(audience.bulkConfirmButton()).toBeDisabled();
    await retry.getByLabel('Type 27 to confirm').fill('27');
    await audience.bulkConfirmButton().click();

    await expect(retry).toBeHidden();
    await expect(audience.contactRow(`Member026 ${family}`)).toContainText('Unsubscribed');
    await expect(audience.contactRow(`Late ${family}`)).toContainText('Unsubscribed');
    await expect(audience.bulkBar()).toBeHidden();
    await audience.gotoSuppressed();
    await expect(audience.suppressionCounts()).toContainText('Unsubscribed · 27');
  });

  test('deleting everyone on the last page goes back to the page before it', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const family = uniqueShort('Paged');
    await importFamily(playwright, freshAccount, family, 27);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts(`?query=${encodeURIComponent(family)}&pageNumber=2`);
    await expect(audience.contactTotal()).toHaveText('27 contacts');
    await expect(audience.contactRows()).toHaveCount(2);

    await audience.selectPageCheckbox().check();
    await expect(audience.bulkBar()).toContainText('2 selected');
    await audience.bulkMoreAction('Delete');
    await page.getByRole('dialog', { name: 'Delete 2 contacts?' }).getByTestId('audience-bulk-confirm').click();

    await expect(audience.contactTotal()).toHaveText('25 contacts');
    await expect(audience.contactRows()).toHaveCount(25);
    await expect(page).not.toHaveURL(/pageNumber=2/);
    await expect(page.getByText('Nobody matches this search or filter.')).toHaveCount(0);
  });
});
