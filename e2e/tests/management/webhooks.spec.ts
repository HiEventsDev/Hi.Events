import { test, expect } from '../../fixtures';
import { WebhookPage } from '../../pages/webhook.page';
test.describe('webhooks', () => {
  test('an organizer creates a webhook and sees it in the table', { tag: '@smoke' }, async ({ authedPage, account }) => {
    const url = `https://example.com/${Math.random().toString(36).slice(2, 10)}`;

    const webhooks = new WebhookPage(authedPage);
    await webhooks.goto(account.organizerId);
    await webhooks.createWebhook(url, 'order.created');

    const row = authedPage.getByRole('row').filter({ hasText: url });
    await expect(row).toBeVisible();
    await expect(row.getByText('ENABLED')).toBeVisible();
  });
});
