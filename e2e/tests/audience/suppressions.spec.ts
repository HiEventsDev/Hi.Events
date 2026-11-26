import type { APIRequestContext, Page } from '@playwright/test';
import { test, expect, type FreshAccount } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail } from '../../utils/unique';
import { AudiencePage } from '../../pages/audience.page';
import { buildAudience, createOptedInOrder, marketingStatusOf, waitForContact, withAudienceApi } from './audience.shared';
import { breakdownValue } from './contacts.helpers';

interface SuppressionSetup {
  fan: string;
  page: Page;
  audience: AudiencePage;
}

const arrangeSubscribedFan = async (freshAccount: FreshAccount, publicApi: APIRequestContext): Promise<string> => {
  const { api, organizerId } = freshAccount;
  const event = await createLiveEventWithFreeTicket(api, organizerId);
  const fan = uniqueEmail('fan');
  await createOptedInOrder(publicApi, event, { email: fan, firstName: 'Fay', lastName: 'Fan' });
  await buildAudience(api, organizerId);
  await waitForContact(api, organizerId, fan);
  return fan;
};

const arrangeImportedSuppressions = async (
  freshAccount: FreshAccount,
  publicApi: APIRequestContext,
  playwright: Parameters<typeof withAudienceApi>[0],
  extraRows = '',
): Promise<SuppressionSetup> => {
  const fan = await arrangeSubscribedFan(freshAccount, publicApi);
  await withAudienceApi(playwright, freshAccount, (audienceApi) =>
    audienceApi.importSuppressions('mailchimp.csv', `Email,Reason\n${fan},\n${extraRows}`));
  const page = await freshAccount.newAuthedPage();
  const audience = new AudiencePage(page, freshAccount.organizerId);
  await audience.gotoSuppressed();
  return { fan, page, audience };
};

test.describe('audience suppression list', () => {
  test('a suppression list is imported after previewing who it affects', { tag: '@smoke' }, async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const fan = await arrangeSubscribedFan(freshAccount, publicApi);
    const bounced = uniqueEmail('bounced');

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await page.goto(`/manage/organizer/${organizerId}/audience/contacts?segment=suppressed`);
    await expect(page).toHaveURL(/segment=suppressed/);
    await expect(page.getByText('Nobody is suppressed.')).toBeVisible();

    const dialog = await audience.openSuppressionImport();
    await audience.chooseCsv(dialog, 'mailchimp.csv', `Email,Reason\n${fan},\n${bounced},cleaned\nnot-an-email,\n`);
    await expect(dialog.getByTestId('audience-suppression-warning')).toHaveText('1 subscribed contact will stop getting campaigns.');
    await expect(dialog.getByTestId('audience-suppression-preview')).toContainText(bounced);
    await dialog.getByTestId('audience-suppression-import-submit').click();

    const result = page.getByTestId('audience-suppression-import-result');
    await expect(result).toContainText('3 rows in the file');
    await expect(breakdownValue(result, 'Contacts suppressed')).toHaveText('1');
    await expect(breakdownValue(result, 'New contacts added as suppressed')).toHaveText('1');
    await expect(breakdownValue(result, 'Skipped: not a valid email address')).toHaveText('1');
    await page.getByTestId('audience-suppression-import-done').click();

    await expect(audience.suppressionCounts()).toContainText('Unsubscribed · 1');
    await expect(audience.suppressionCounts()).toContainText('Bounced · 1');
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(audience.contactRow(fan)).toContainText('Unsubscribed');
    await expect(audience.contactRow(fan)).toContainText('Imported from mailchimp.csv');
    await expect(audience.contactRow(bounced)).toContainText('Bounced');
    await expect.poll(() => marketingStatusOf(api, organizerId, fan)).toBe('UNSUBSCRIBED');
  });

  test('importing a list again reports who was already suppressed and offers another file', async ({ freshAccount, publicApi, playwright }) => {
    const { fan, page, audience } = await arrangeImportedSuppressions(freshAccount, publicApi, playwright);
    const complainer = uniqueEmail('complainer');

    const dialog = await audience.openSuppressionImport();
    await expect(dialog.getByRole('listitem').filter({ hasText: 'mailchimp.csv' })).toContainText('Suppression list · 1 contact suppressed');
    await audience.chooseCsv(dialog, 'second.csv', `Email,Reason\n${fan},\n${complainer},spam\n${complainer},spam\n`);
    const preview = dialog.getByTestId('audience-suppression-preview');
    await expect(breakdownValue(preview, 'Skipped: listed more than once in the file')).toHaveText('1');
    await expect(dialog.getByTestId('audience-suppression-warning')).toHaveCount(0);
    await dialog.getByTestId('audience-suppression-import-submit').click();

    const result = page.getByTestId('audience-suppression-import-result');
    await expect(breakdownValue(result, 'Already suppressed')).toHaveText('1');
    await expect(breakdownValue(result, 'New contacts added as suppressed')).toHaveText('1');
    await expect(breakdownValue(result, 'Skipped: listed more than once in the file')).toHaveText('1');
    await page.getByRole('button', { name: 'Import another file' }).click();

    await expect(dialog.getByRole('listitem').filter({ hasText: 'second.csv' })).toContainText('Suppression list · 1 contact suppressed · 2 rows skipped');
    await expect(dialog.getByTestId('audience-suppression-import-submit')).toBeDisabled();
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();

    await expect(audience.suppressionCounts()).toContainText('Marked as spam · 1');
    await expect(audience.contactTotal()).toHaveText('2 contacts');
    await expect(audience.contactRow(complainer)).toContainText('Imported from second.csv');
  });

  test('pasted addresses are checked against the suppression list', async ({ freshAccount, publicApi, playwright }) => {
    const { fan, page, audience } = await arrangeImportedSuppressions(freshAccount, publicApi, playwright);
    const stranger = uniqueEmail('stranger');

    await audience.contactsMoreActions().click();
    await page.getByTestId('audience-check-addresses-button').click();
    await page.getByTestId('audience-check-addresses-input').fill(`${fan}\n${stranger}`);
    await page.getByTestId('audience-check-addresses-submit').click();

    const results = page.getByTestId('audience-check-addresses-results');
    await expect(results.getByRole('listitem').filter({ hasText: fan })).toContainText('Imported from mailchimp.csv');
    await expect(results.getByRole('listitem').filter({ hasText: stranger })).toContainText('Not in your audience');
  });

  test('a bounced address is reactivated to its consent from before the bounce', async ({ freshAccount, publicApi, playwright }) => {
    const bounced = uniqueEmail('bounced');
    const { page, audience } = await arrangeImportedSuppressions(freshAccount, publicApi, playwright, `${bounced},cleaned\n`);

    await audience.suppressionCounts().getByText('Bounced · 1').click();
    await expect(audience.contactRows()).toHaveCount(1);
    await audience.contactRow(bounced).click();
    await page.getByTestId('contact-reactivate-bounce').click();
    const reactivate = page.getByRole('dialog', { name: `Reactivate ${bounced}?` });
    await expect(reactivate).toContainText('Before the bounce: never opted in.');
    await reactivate.getByRole('button', { name: 'Reactivate', exact: true }).click();

    await expect(reactivate).toBeHidden();
    await expect(audience.marketingStatus()).toContainText('Not subscribed');
    await expect(page.getByTestId('contact-reactivate-bounce')).toHaveCount(0);
    await page.keyboard.press('Escape');
    await expect(audience.contactRow(bounced)).toHaveCount(0);
    await expect(audience.suppressionCounts()).toContainText('Bounced · 0');
    await expect.poll(() => marketingStatusOf(freshAccount.api, freshAccount.organizerId, bounced)).toBe('NOT_SUBSCRIBED');
  });

  test('a deleted suppressed contact stays suppressed', async ({ freshAccount, publicApi, playwright }) => {
    const { fan, page, audience } = await arrangeImportedSuppressions(freshAccount, publicApi, playwright);
    await expect(audience.contactTotal()).toHaveText('1 contact');

    await audience.rowCheckbox(fan).check();
    await expect(audience.bulkBar().getByRole('button', { name: 'Add tag' })).toHaveCount(0);
    await audience.bulkMoreAction('Delete');
    await audience.bulkConfirmButton().click();

    await expect(page.getByText('Nobody is suppressed.')).toBeVisible();
    await expect(audience.suppressionCounts()).toContainText('1 deleted contact also stays suppressed.');

    await audience.suppressionCounts().getByRole('button', { name: 'Check an address' }).click();
    await page.getByTestId('audience-check-addresses-input').fill(fan);
    await page.getByTestId('audience-check-addresses-submit').click();
    await expect(page.getByTestId('audience-check-addresses-results').getByRole('listitem').filter({ hasText: fan }))
      .toContainText('Deleted contact. They had unsubscribed before they were deleted, so they stay suppressed.');
  });
});
