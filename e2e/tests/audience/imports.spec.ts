import { test, expect } from '../../fixtures';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, withAudienceApi } from './audience.shared';
import { breakdownValue, contactsCsv, familyCsv, importThroughDialog, openImportContacts, withContactsApi } from './contacts.helpers';

test.describe('audience contact imports', () => {
  test('contacts are imported from a CSV file as not opted in, and unusable rows are counted', async ({ freshAccount }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const imported = uniqueEmail('imported');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    const result = await importThroughDialog(page, audience, `Email,First Name,Last Name\n${imported},Ivy,Imported\nnot-an-email,No,Body\n`);

    await expect(page.getByRole('dialog', { name: 'Import finished' })).toBeVisible();
    await expect(result).toContainText('2 rows in the file');
    await expect(breakdownValue(result, 'Added to your audience')).toHaveText('1');
    await expect(breakdownValue(result, 'Skipped: not a valid email address')).toHaveText('1');
    await expect(result.getByText('Already in your audience')).toHaveCount(0);
    await audience.finishImport();

    await expect(audience.contactTotal()).toHaveText('1 contact');
    await expect(audience.contactRow('Ivy Imported')).toContainText('Not subscribed');
    await audience.contactRow('Ivy Imported').click();
    await expect(audience.marketingStatus()).toContainText('Not subscribed');
    await expect(audience.drawer()).toContainText(imported);
  });

  test('an import with consent confirmed adds subscribers under a tag, skips repeated and deleted people, and is listed under recent imports', async ({ freshAccount, playwright }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const fresh = uniqueEmail('fresh');
    const known = uniqueEmail('known');
    const gone = uniqueEmail('gone');
    await withAudienceApi(playwright, freshAccount, async (audienceApi) => {
      await audienceApi.createContact({ email: known, first_name: 'Kay', last_name: 'Known' });
      await audienceApi.createContact({ email: gone, first_name: 'Gus', last_name: 'Gone' });
    });
    const [deleted] = await api.findAudienceContacts(organizerId, gone);
    await withContactsApi(playwright, freshAccount, (contactsApi) => contactsApi.deleteContact(deleted.id));
    const tag = uniqueShort('Newsletter');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    await expect(audience.contactTotal()).toHaveText('1 contact');
    const csv = contactsCsv([
      { email: fresh, firstName: 'Fern', lastName: 'Fresh' },
      { email: fresh.toUpperCase(), firstName: 'Fern', lastName: 'Fresh' },
      { email: known, firstName: 'Kay', lastName: 'Known' },
      { email: gone, firstName: 'Gus', lastName: 'Gone' },
    ]);
    const result = await importThroughDialog(page, audience, csv, { fileName: 'fans.csv', tag, consentAttested: true });

    await expect(result).toContainText('4 rows in the file');
    await expect(breakdownValue(result, 'Added to your audience')).toHaveText('1');
    await expect(breakdownValue(result, 'Already in your audience')).toHaveText('1');
    await expect(breakdownValue(result, 'Skipped: listed more than once in the file')).toHaveText('1');
    await expect(breakdownValue(result, 'Skipped: deleted from your audience earlier')).toHaveText('1');
    await expect(result).toContainText("People you deleted aren't added back by imports.");
    await audience.finishImport();

    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(audience.contactRow(gone)).toHaveCount(0);
    await expect(audience.contactRow(fresh)).toContainText('Subscribed');
    await expect(audience.contactRow(fresh)).toContainText(tag);
    await expect(audience.contactRow(known)).toContainText('Not subscribed');

    await audience.contactRow(fresh).click();
    await expect(audience.marketingStatus()).toContainText('Subscribed');
    await expect(audience.drawerSection('Consent history')).toContainText('Imported from fans.csv, with consent confirmed');
    await page.keyboard.press('Escape');

    const dialog = await openImportContacts(page, audience);
    const recent = dialog.getByRole('listitem').filter({ hasText: 'fans.csv' });
    await expect(recent).toContainText('1 contact added · 1 already existed · 2 rows skipped');
    await expect(recent).toContainText('by E2E Organizer');
  });

  test('a file of more than 500 rows is imported in the background and the contacts appear when it finishes', async ({ freshAccount }) => {
    test.slow();
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const family = uniqueShort('Crowd');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContactsMatching(family);
    await expect(page.getByText('Nobody matches this search or filter.')).toBeVisible();
    const upload = page.waitForResponse((response) => response.request().method() === 'POST' && /\/crm\/imports$/.test(new URL(response.url()).pathname));
    const result = await importThroughDialog(page, audience, familyCsv(family, 520), { fileName: 'crowd.csv' });

    expect(((await (await upload).json()) as { data: { status: string } }).data.status).toBe('PROCESSING');
    await expect(page.getByRole('dialog', { name: 'Import finished' })).toBeVisible({ timeout: 90_000 });
    await expect(result).toContainText('520 rows in the file');
    await expect(breakdownValue(result, 'Added to your audience')).toHaveText('520');
    await audience.finishImport();

    await expect(audience.contactTotal()).toHaveText('520 contacts');
    await expect(audience.contactRows()).toHaveCount(25);
    await page.getByRole('button', { name: '21', exact: true }).click();
    await expect(page).toHaveURL(/pageNumber=21/);
    await expect(audience.contactRows()).toHaveCount(20);

    const dialog = await openImportContacts(page, audience);
    await expect(dialog.getByRole('listitem').filter({ hasText: 'crowd.csv' })).toContainText('520 contacts added');
  });

  test('the import dialog leads to the suppression import for people who must not be emailed', async ({ freshAccount }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const blocked = uniqueEmail('blocked');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoContacts();
    const contacts = await openImportContacts(page, audience);
    await expect(page.getByTestId('audience-import-submit')).toBeDisabled();
    await contacts.getByRole('button', { name: 'Import a suppression list instead' }).click();

    const suppressions = page.getByRole('dialog', { name: 'Import suppressions' });
    await expect(contacts).toBeHidden();
    await audience.chooseCsv(suppressions, 'do-not-email.csv', `Email,Reason\n${blocked},spam\n`);
    await expect(suppressions.getByTestId('audience-suppression-preview')).toContainText('Marked as spam');
    await expect(page.getByTestId('audience-suppression-import-submit')).toHaveText('Suppress 1 address');
    await page.getByTestId('audience-suppression-import-submit').click();
    await expect(breakdownValue(page.getByTestId('audience-suppression-import-result'), 'New contacts added as suppressed')).toHaveText('1');
    await page.getByTestId('audience-suppression-import-done').click();

    await audience.gotoSuppressed();
    await expect(audience.contactRow(blocked)).toContainText('Marked as spam');
  });
});
