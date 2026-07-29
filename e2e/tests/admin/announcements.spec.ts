import { test, expect } from '../../fixtures';
import { uniqueName } from '../../utils/unique';

test.describe('announcements', () => {
  const bannerTitle = uniqueName('E2E Banner');
  const modalTitle = uniqueName('E2E Modal');

  test(
    'a targeted user sees, closes and dismisses announcements, and the admin tally updates',
    { tag: '@admin' },
    async ({ adminApi, freshAccount, superAdminPage }) => {
      const created: number[] = [];

      try {
        const banner = await adminApi.createAnnouncement({
          title: bannerTitle,
          content: 'We shipped something new for you',
          status: 'PUBLISHED',
          display_type: 'BANNER',
          target_type: 'USERS',
          target_user_ids: [freshAccount.userId],
          cta_label: 'Learn more',
          cta_url: 'https://hi.events/changelog',
        });
        created.push(banner.id);
        const modal = await adminApi.createAnnouncement({
          title: modalTitle,
          content: '<p>Here is a rich announcement</p>',
          status: 'PUBLISHED',
          display_type: 'MODAL',
          emoji: '🎉',
          target_type: 'USERS',
          target_user_ids: [freshAccount.userId],
        });
        created.push(modal.id);

        const page = await freshAccount.newAuthedPage();
        await page.goto('/manage/events');

        const announcementModal = page.getByTestId('announcement-modal');
        await expect(announcementModal).toBeVisible();
        await expect(announcementModal.getByText(modalTitle)).toBeVisible();
        await expect(announcementModal.getByText('Here is a rich announcement')).toBeVisible();
        await expect(page.getByTestId('announcement-banner')).toHaveCount(0);

        await page.keyboard.press('Escape');
        await expect(announcementModal).toHaveCount(0);

        const bannerStrip = page.getByTestId('announcement-banner');
        await expect(bannerStrip).toBeVisible();
        await expect(bannerStrip.getByText(bannerTitle)).toBeVisible();
        await expect(page.getByTestId('announcement-banner-cta')).toHaveAttribute(
          'href',
          'https://hi.events/changelog',
        );

        await page.reload();
        await expect(page.getByTestId('announcement-modal')).toBeVisible();
        await Promise.all([
          page.waitForResponse((response) => response.url().includes('/dismiss') && response.ok()),
          page.getByTestId('announcement-modal-dismiss').click(),
        ]);
        await expect(page.getByTestId('announcement-modal')).toHaveCount(0);

        await Promise.all([
          page.waitForResponse((response) => response.url().includes('/dismiss') && response.ok()),
          page.getByTestId('announcement-banner-dismiss').click(),
        ]);
        await expect(page.getByTestId('announcement-banner')).toHaveCount(0);

        const activeAfterDismiss = page.waitForResponse(
          (response) => response.url().includes('/announcements/active') && response.ok(),
        );
        await page.reload();
        await activeAfterDismiss;
        await expect(page.getByTestId('announcement-modal').getByText(modalTitle)).toHaveCount(0);
        await expect(page.getByTestId('announcement-banner').getByText(bannerTitle)).toHaveCount(0);

        await superAdminPage.goto('/admin/announcements');
        await expect(superAdminPage.getByRole('heading', { name: 'Announcements', exact: true })).toBeVisible();
        await expect(superAdminPage.getByTestId('announcement-modal')).toHaveCount(0);
        await expect(superAdminPage.getByTestId('announcement-banner')).toHaveCount(0);

        await superAdminPage.getByPlaceholder('Search by title...').fill(modalTitle);
        const modalRow = superAdminPage.getByRole('row').filter({ hasText: modalTitle });
        await expect(modalRow).toBeVisible();
        await expect(modalRow.getByText('Users (1)')).toBeVisible();
        await expect(modalRow.getByRole('cell', { name: '1', exact: true })).toHaveCount(2);
      } finally {
        const results = await Promise.allSettled(created.map((id) => adminApi.deleteAnnouncement(id)));
        const failed = results.find((result) => result.status === 'rejected');
        if (failed) throw (failed as PromiseRejectedResult).reason;
      }
    },
  );
});
