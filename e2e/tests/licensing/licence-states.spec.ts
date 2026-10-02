import type { Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createDraftEvent, createDraftEventWithTicket, createFixtureSeatMap } from '../../api/factory';
import type { AdminApiClient, ApiClient } from '../../api/api-client';
import { nonSaasOnly, saasOnly } from '../../utils/mode';
import { simulateLicence, withLicence } from '../../utils/licence';
import { BoxOfficeManagePage } from '../../pages/box-office-manage.page';

const licenceBadge = (page: Page) => page.getByTestId('licence-badge');

async function openAccountMenu(page: Page): Promise<void> {
  await page.getByTestId('account-menu-button').click();
  await expect(page.getByRole('menuitem', { name: 'My Profile' })).toBeVisible();
}

async function openLicenceModal(page: Page) {
  await openAccountMenu(page);
  await page.getByTestId('licence-menu-item').click();
  const modal = page.getByTestId('licence-modal');
  await expect(modal).toBeVisible();
  return modal;
}

async function expectNoLicenceMenuItem(page: Page): Promise<void> {
  await openAccountMenu(page);
  await expect(page.getByTestId('licence-menu-item')).toHaveCount(0);
  await page.keyboard.press('Escape');
}

async function enableEnterpriseFlags(adminApi: AdminApiClient, email: string): Promise<void> {
  const accountId = await adminApi.findAccountIdByEmail(email);
  await adminApi.setAccountFeatureFlag(accountId, 'seating', true);
  await adminApi.setAccountFeatureFlag(accountId, 'box_office', true);
}

async function createEventWithBoxOffice(api: ApiClient, organizerId: number): Promise<number> {
  const { eventId } = await createDraftEvent(api, organizerId);
  await api.createBoxOffice(eventId, { name: 'Side door' });
  return eventId;
}

async function openOrderFilters(page: Page, eventId: number) {
  await page.goto(`/manage/event/${eventId}/orders`);
  await page.getByRole('button', { name: 'Filters' }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText('Refund Status')).toBeVisible();
  return dialog;
}

test.describe('licence gating', () => {
  test('an account that never set up an enterprise feature sees nothing enterprise without a licence', { tag: '@smoke' }, async ({ freshAccount, playwright }) => {
    const { eventId } = await createDraftEvent(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'NONE');

    const filters = await openOrderFilters(page, eventId);
    await expect(filters.getByText('Sold by')).toHaveCount(0);
    await expect(filters.getByText('Box Office')).toHaveCount(0);
    await page.keyboard.press('Escape');

    await expect(page.getByRole('link', { name: 'Attendees' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Box Office' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Seating', exact: true })).toHaveCount(0);
    await expect(licenceBadge(page)).toHaveCount(0);

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/settings`);
    await expect(page.getByText('Basic Information').first()).toBeVisible();
    await expect(page.getByText('Card Readers')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Seat Maps' })).toHaveCount(0);
    await expect(licenceBadge(page)).toHaveCount(0);
    await expectNoLicenceMenuItem(page);

    const me = await withLicence(playwright, freshAccount.token, 'NONE', api => api.getMe());
    expect(me.licence).toMatchObject({ status: 'NONE', invalid_reason: null, features_in_use: [] });
  });

  test('a box office set up without a licence stays visible but its setup is locked', async ({ freshAccount, adminApi, playwright }) => {
    await enableEnterpriseFlags(adminApi, freshAccount.email);
    const eventId = await createEventWithBoxOffice(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'NONE');

    await page.goto(`/manage/event/${eventId}/box-office`);
    await expect(page.getByTestId('licence-locked-callout')).toBeVisible();
    await expect(page.getByText('Side door')).toBeVisible();
    await expect(page.getByTestId('box-office-create-button')).toHaveCount(0);
    await expect(page.getByTestId('box-office-open-default-button')).toHaveCount(0);
    await page.getByText('Side door').click();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Box Office' })).toBeVisible();

    const filters = await openOrderFilters(page, eventId);
    await expect(filters.getByText('Sold by')).toBeVisible();

    await withLicence(playwright, freshAccount.token, 'NONE', async api => {
      await expect(api.createBoxOffice(eventId, { name: 'Blocked' })).rejects.toThrow(/→ 403/);
      expect((await api.listBoxOffices(eventId)).map(boxOffice => boxOffice.name)).toContain('Side door');
    });
  });

  test('direct links to enterprise setup stay locked without a licence', async ({ freshAccount, adminApi }) => {
    await enableEnterpriseFlags(adminApi, freshAccount.email);
    const { eventId } = await createDraftEvent(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'NONE');

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/seat-maps`);
    await expect(page.getByTestId('licence-locked-callout')).toBeVisible();
    await expect(page.getByTestId('seat-map-create-button')).toHaveCount(0);

    await page.goto(`/manage/event/${eventId}/box-office`);
    await expect(page.getByTestId('licence-locked-callout')).toBeVisible();
    await expect(page.getByTestId('box-office-create-button')).toHaveCount(0);

    await page.goto(`/manage/event/${eventId}/seating`);
    await expect(page.getByTestId('licence-locked-callout')).toBeVisible();
    await expect(page.getByTestId('seating-attach-button')).toHaveCount(0);
    await expect(page.getByTestId('seating-create-seat-map-button')).toHaveCount(0);
  });

  test('a box office refused by the feature flag is explained instead of leaving the page', async ({ freshAccount, adminApi }) => {
    const accountId = await adminApi.findAccountIdByEmail(freshAccount.email);
    await adminApi.setAccountFeatureFlag(accountId, 'box_office', false);
    const { eventId } = await createDraftEventWithTicket(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'CLOUD');

    const boxOffices = new BoxOfficeManagePage(page);
    await boxOffices.goto(eventId);
    await boxOffices.create('Refused door');

    await expect(page.getByText('This feature is not enabled for this account')).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`/manage/event/${eventId}/box-office`));
  });

  test('a seat map template set up without a licence can still be deleted', async ({ freshAccount, adminApi }) => {
    await enableEnterpriseFlags(adminApi, freshAccount.email);
    const seatMapId = await createFixtureSeatMap(freshAccount.api, freshAccount.organizerId, 'theatre', 'Old hall');
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'NONE');

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/seat-maps`);
    await expect(page.getByTestId('licence-locked-callout')).toBeVisible();
    await expect(page.getByText('Old hall')).toBeVisible();
    await expect(page.getByTestId('seat-map-create-button')).toHaveCount(0);
    await expect(page.getByTestId(`seat-map-edit-button-${seatMapId}`)).toHaveCount(0);

    await page.getByTestId(`seat-map-delete-button-${seatMapId}`).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Confirm' }).click();
    await expect(page.getByText('Seat map deleted')).toBeVisible();

    await page.reload();
    await expect(page.getByRole('link', { name: 'Locations' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Seat Maps' })).toHaveCount(0);
  });

  test('the grace period keeps setup unlocked', async ({ freshAccount, adminApi }) => {
    await enableEnterpriseFlags(adminApi, freshAccount.email);
    const eventId = await createEventWithBoxOffice(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'GRACE');

    await page.goto(`/manage/event/${eventId}/box-office`);
    await expect(page.getByTestId('box-office-create-button')).toBeVisible();
    await expect(page.getByTestId('licence-locked-callout')).toHaveCount(0);
  });

  test('the admin licence page warns about an upcoming expiry', async ({ superAdminPage }) => {
    await simulateLicence(superAdminPage, 'EXPIRING');

    await superAdminPage.goto('/admin/licence');
    await expect(superAdminPage.getByTestId('admin-licence-status')).toHaveText('Active');
    await expect(superAdminPage.getByText('This licence expires on')).toBeVisible();
  });

  test('the Powered by notice follows the white-label licence', async ({ page }) => {
    await simulateLicence(page, 'NONE');
    await page.goto('/auth/login');
    await expect(page.getByText('Powered by')).toBeVisible();
    await expect(page.getByText('Development licence, not for production use')).toHaveCount(0);

    await simulateLicence(page, 'ACTIVE:white_label');
    await page.goto('/auth/login');
    await expect(page.getByRole('button', { name: /log in/i })).toBeVisible();
    await expect(page.getByText('Powered by')).toHaveCount(0);
  });

  test('the public footer flags a development licence once an enterprise feature is in use', async ({ freshAccount, adminApi, page }) => {
    await enableEnterpriseFlags(adminApi, freshAccount.email);
    await createEventWithBoxOffice(freshAccount.api, freshAccount.organizerId);

    await simulateLicence(page, 'DEV');
    await page.goto('/auth/login');
    await expect(page.getByText('Development licence, not for production use')).toBeVisible();
  });
});

test.describe('licence notices', () => {
  nonSaasOnly();

  test('an unlicensed box office is flagged to the account using it', { tag: '@smoke' }, async ({ freshAccount }) => {
    const eventId = await createEventWithBoxOffice(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'NONE');

    await page.goto(`/manage/event/${eventId}/box-office`);
    await expect(licenceBadge(page)).toHaveAttribute('data-tone', 'danger');

    const modal = await openLicenceModal(page);
    await expect(modal).toContainText('Setup for box office is locked');
    await expect(modal.getByRole('link', { name: 'Get a licence' })).toHaveAttribute('href', /hi\.events\/licensing/);
  });

  test('deleting the last seat map template clears the unlicensed notice', async ({ freshAccount }) => {
    const seatMapId = await createFixtureSeatMap(freshAccount.api, freshAccount.organizerId, 'theatre', 'Old hall');
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'NONE');

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/seat-maps`);
    await expect(licenceBadge(page)).toHaveAttribute('data-tone', 'danger');

    await page.getByTestId(`seat-map-delete-button-${seatMapId}`).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Confirm' }).click();
    await expect(page.getByText('Seat map deleted')).toBeVisible();

    await page.reload();
    await expect(page.getByRole('link', { name: 'Locations' })).toBeVisible();
    await expect(licenceBadge(page)).toHaveCount(0);
    await expectNoLicenceMenuItem(page);
  });

  test('the grace period warns only accounts using enterprise features', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'GRACE');

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/events`);
    await expect(page.getByRole('link', { name: 'Locations' })).toBeVisible();
    await expect(licenceBadge(page)).toHaveCount(0);
    await expectNoLicenceMenuItem(page);

    const eventId = await createEventWithBoxOffice(freshAccount.api, freshAccount.organizerId);
    await page.goto(`/manage/event/${eventId}/box-office`);
    await expect(licenceBadge(page)).toHaveAttribute('data-tone', 'warning');

    const modal = await openLicenceModal(page);
    await expect(modal).toContainText('Everything stays on until');
    await expect(modal.getByRole('link', { name: 'Renew licence' })).toHaveAttribute('href', /hi\.events\/licensing/);
  });

  test('an active licence shows no licence notice, even close to expiry', async ({ freshAccount }) => {
    const eventId = await createEventWithBoxOffice(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'EXPIRING');

    await page.goto(`/manage/event/${eventId}/box-office`);
    await expect(page.getByTestId('box-office-create-button')).toBeVisible();
    await expect(licenceBadge(page)).toHaveCount(0);
    await expectNoLicenceMenuItem(page);
  });

  test('an invalid licence key is explained to admins', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'INVALID');

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/events`);
    await expect(licenceBadge(page)).toHaveAttribute('data-tone', 'danger');

    const modal = await openLicenceModal(page);
    await expect(modal).toContainText("the key couldn't be verified");
    await expect(modal).toContainText('The licence key signature is invalid');
  });

  test('a development licence is flagged once an enterprise feature is in use', async ({ freshAccount }) => {
    const eventId = await createEventWithBoxOffice(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'DEV');

    await page.goto(`/manage/event/${eventId}/box-office`);
    await expect(licenceBadge(page)).toHaveAttribute('data-tone', 'info');

    const modal = await openLicenceModal(page);
    await expect(modal).toContainText('APP_LICENCE_KEY=development unlocks reserved seating and the box office for development and testing');
    await expect(modal).toContainText('The rest of Hi.Events is free for real events');
    await expect(modal).toContainText('Remove APP_LICENCE_KEY and redeploy');
    await expect(modal.getByRole('link', { name: 'Get a licence' })).toHaveAttribute('href', /hi\.events\/licensing/);
  });

  test('a lapsed white-label licence explains why the Powered by notice is back', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await simulateLicence(page, 'LAPSED:white_label');

    await page.goto(`/manage/organizer/${freshAccount.organizerId}/events`);
    await expect(licenceBadge(page)).toHaveAttribute('data-tone', 'danger');

    const modal = await openLicenceModal(page);
    await expect(modal).toContainText('"Powered by Hi.Events" notice is shown again');
  });
});

test.describe('licence notices on a SaaS install', () => {
  saasOnly();

  test('tenant admins never see the operator licence notices', async ({ freshAccount }) => {
    const eventId = await createEventWithBoxOffice(freshAccount.api, freshAccount.organizerId);
    const page = await freshAccount.newAuthedPage();

    await page.goto(`/manage/event/${eventId}/box-office`);
    await expect(page.getByText('Side door')).toBeVisible();
    await expect(licenceBadge(page)).toHaveCount(0);
    await expectNoLicenceMenuItem(page);
  });
});
