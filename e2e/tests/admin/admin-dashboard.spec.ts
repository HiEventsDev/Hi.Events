import { test, expect } from '../../fixtures';
import { AdminPage } from '../../pages/admin.page';
import { createCompletedOrder, createLiveEventWithProduct } from '../../api/factory';
import { uniqueName } from '../../utils/unique';

test.describe('admin dashboard', () => {
  test('a superadmin views platform stats and drills into an account', { tag: ['@smoke', '@admin'] }, async ({ superAdminPage }) => {
    const admin = new AdminPage(superAdminPage);
    await admin.gotoDashboard();

    await expect(superAdminPage.getByRole('heading', { name: 'Admin Dashboard', exact: true })).toBeVisible();
    await expect(superAdminPage.getByText('Total Users')).toBeVisible();
    await expect(superAdminPage.getByText('Total Accounts')).toBeVisible();

    await admin.openAccountsFromSidebar();
    await expect(superAdminPage.getByRole('heading', { name: 'Accounts', exact: true })).toBeVisible();
    await expect(admin.viewDetailsButtons().first()).toBeVisible();

    await admin.viewDetailsButtons().first().click();
    await expect(superAdminPage.getByText('Account Information')).toBeVisible();
    await expect(superAdminPage.getByRole('button', { name: 'Back to Accounts' })).toBeVisible();
  });

  test('users, events and orders admin pages render results', { tag: '@admin' }, async ({ superAdminPage, api, account, publicApi }) => {
    const event = await createLiveEventWithProduct(api, {
      organizerId: account.organizerId,
      title: uniqueName('Admin Event'),
    });
    const order = await createCompletedOrder(publicApi, event);

    const admin = new AdminPage(superAdminPage);

    await admin.gotoSection('users');
    await expect(superAdminPage.getByRole('heading', { name: 'Users', exact: true })).toBeVisible();
    await admin.search(account.email);
    await expect(superAdminPage.getByText(account.email).first()).toBeVisible();

    await admin.gotoSection('events');
    await expect(superAdminPage.getByRole('heading', { name: 'Events', exact: true })).toBeVisible();
    await admin.search(event.title);
    await expect(superAdminPage.getByRole('row').filter({ hasText: event.title }).first()).toBeVisible();

    await admin.gotoSection('orders');
    await expect(superAdminPage.getByRole('heading', { name: 'Orders', exact: true })).toBeVisible();
    await admin.search(order.buyerEmail);
    await expect(superAdminPage.getByRole('row').filter({ hasText: order.buyerEmail }).first()).toBeVisible();
  });
});
