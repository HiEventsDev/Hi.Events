import { test, expect } from '../../fixtures';
import { invitedMember } from '../../utils/team';

test.describe('account pending deletion', () => {
  test('a restricted member lands on their profile once instead of reloading forever', async ({ freshAccount, mailpit, publicApi, browser }) => {
    const { page } = await invitedMember(freshAccount, mailpit, publicApi, browser, {
      role: 'ORGANIZER',
      access_scope: 'RESTRICTED',
      organizer_ids: [freshAccount.organizerId],
    });
    const account = await freshAccount.api.getAccount();
    await freshAccount.api.requestAccountDeletion(account.name);

    let loads = 0;
    page.on('load', () => loads++);
    await page.goto('/manage/events');

    await expect(page).toHaveURL(/\/settings\/profile/);
    await expect(page.getByText('This account is scheduled for deletion on')).toBeVisible();
    await expect(page.getByTestId('pending-deletion-banner-button')).toHaveCount(0);
    const loadsOnceSettled = loads;
    await page.waitForTimeout(3_000);
    expect(loads).toBe(loadsOnceSettled);
    await expect(page).toHaveURL(/\/settings\/profile/);

    const ownerPage = await freshAccount.newAuthedPage();
    await ownerPage.goto('/manage/events');
    await expect(ownerPage).toHaveURL(/\/settings\/account#delete-account$/);
    await expect(ownerPage.getByTestId('cancel-account-deletion-button')).toBeVisible();
  });
});
