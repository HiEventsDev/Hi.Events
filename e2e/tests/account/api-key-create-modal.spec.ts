import { expect, test } from '../../fixtures';
import { ApiKeysPage } from '../../pages/api-keys.page';

const REMINDER = "We'll email you a week before.";

test.describe('Create API key modal', () => {
  test('only promises an expiry reminder for keys that get one', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await apiKeys.openCreate();

    await apiKeys.fillCreateForm({ name: 'Short lived', expiry: '7 days', password: freshAccount.password });
    await expect(apiKeys.dialog()).toContainText('Stops working on');
    await expect(apiKeys.dialog()).not.toContainText(REMINDER);

    await apiKeys.fillCreateForm({ name: 'Short lived', expiry: '30 days', password: freshAccount.password });
    await expect(apiKeys.dialog()).toContainText(REMINDER);
  });

  test('the one-time key reveal moves focus to the copy button', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const apiKeys = new ApiKeysPage(page);

    await apiKeys.goto();
    await apiKeys.openCreate();
    await apiKeys.fillCreateForm({ name: 'Focus check', password: freshAccount.password });
    await apiKeys.submitCreate();

    await expect(apiKeys.dialog().getByRole('heading', { name: 'Copy your API key' })).toBeVisible();
    await expect(page.getByTestId('api-key-copy')).toBeFocused();
  });
});
