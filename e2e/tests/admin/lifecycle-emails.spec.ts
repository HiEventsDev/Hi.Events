import { test, expect } from '../../fixtures';
import { AdminPage } from '../../pages/admin.page';
import { IS_SAAS_MODE } from '../../utils/env';

test.describe('admin lifecycle emails', () => {
  test.skip(!IS_SAAS_MODE, 'Lifecycle emails only exist in SaaS mode.');

  test('a superadmin edits a template, sees the live preview and saves it', { tag: '@admin' }, async ({ superAdminPage }) => {
    const marker = `E2E ${Date.now()}`;
    const admin = new AdminPage(superAdminPage);

    await admin.gotoLifecycleEmails();
    await admin.selectLifecycleEmail('EVENT_DRAFT_STALE');

    await admin.lifecycleEmailSubject().fill(`${marker} {{ event.title }}`);
    await expect(superAdminPage.getByText(`${marker} Summer Rooftop Sessions`)).toBeVisible();
    await expect(admin.lifecycleEmailPreviewFrame().getByText('Summer Rooftop Sessions').first()).toBeVisible();

    await admin.saveLifecycleEmail();
    await expect(superAdminPage.getByText('Email saved')).toBeVisible();

    await admin.gotoLifecycleEmails();
    await admin.selectLifecycleEmail('EVENT_DRAFT_STALE');
    await expect(admin.lifecycleEmailSubject()).toHaveValue(`${marker} {{ event.title }}`);
  });
});
