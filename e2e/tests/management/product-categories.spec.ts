import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createCompletedOrder, createDraftEventWithTicket, createLiveEventWithFreeTicket } from '../../api/factory';
import { uniqueEmail, uniqueShort } from '../../utils/unique';

const gotoProducts = async (page: Page, eventId: number): Promise<void> => {
  await page.goto(`/manage/event/${eventId}/products`);
  await page.waitForLoadState('networkidle');
};

const createCategory = async (page: Page, name: string): Promise<void> => {
  await page.getByTestId('product-create-button').click();
  await page.getByRole('menuitem', { name: 'Category' }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('heading', { name: 'Create Category' }).waitFor();
  await dialog.getByLabel(/^Name/).fill(name);
  await dialog.getByRole('button', { name: 'Create Category' }).click();
  await dialog.waitFor({ state: 'hidden' });
};

const categoryHeading = (page: Page, name: string): Locator =>
  page.getByRole('heading', { name, exact: true });

const categoryAction = (page: Page, name: string, action: string): Locator =>
  categoryHeading(page, name).locator('..').getByRole('button', { name: action });

test.describe('product categories', () => {
  test('an organizer creates a product category', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const name = uniqueShort('Merch');

    await gotoProducts(authedPage, event.eventId);
    await createCategory(authedPage, name);

    await expect(categoryHeading(authedPage, name)).toBeVisible();
  });

  test('an organizer renames a product category', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const [defaultCategory] = await api.listProductCategories(event.eventId);
    const newName = uniqueShort('Renamed');

    await gotoProducts(authedPage, event.eventId);
    await categoryAction(authedPage, defaultCategory.name, 'Edit category').click();

    const dialog = authedPage.getByRole('dialog');
    await expect(dialog.getByRole('heading', { name: 'Edit Product Category' })).toBeVisible();
    await expect(dialog.getByLabel(/^Name/)).toHaveValue(defaultCategory.name);
    await dialog.getByLabel(/^Name/).fill(newName);
    await dialog.getByRole('button', { name: 'Edit Product Category' }).click();

    await expect(categoryHeading(authedPage, newName)).toBeVisible();
    await expect(categoryHeading(authedPage, defaultCategory.name)).toBeHidden();
  });

  test('an organizer deletes an empty product category', async ({ authedPage, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const [defaultCategory] = await api.listProductCategories(event.eventId);
    const name = uniqueShort('Extra');

    await gotoProducts(authedPage, event.eventId);
    await createCategory(authedPage, name);
    await expect(categoryHeading(authedPage, name)).toBeVisible();

    await categoryAction(authedPage, name, 'Delete category').click();
    await authedPage.getByRole('button', { name: 'Confirm' }).click();

    await expect(categoryHeading(authedPage, name)).toBeHidden();
    await expect(categoryHeading(authedPage, defaultCategory.name)).toBeVisible();
  });

  test('an organizer cannot delete a category whose product has orders', async ({ authedPage, api, account, publicApi }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    await createCompletedOrder(publicApi, event, { buyerEmail: uniqueEmail() });
    const [defaultCategory] = await api.listProductCategories(event.eventId);
    const name = uniqueShort('Second');

    await gotoProducts(authedPage, event.eventId);
    await createCategory(authedPage, name);
    await expect(categoryHeading(authedPage, name)).toBeVisible();

    await categoryAction(authedPage, defaultCategory.name, 'Delete category').click();
    await authedPage.getByRole('button', { name: 'Confirm' }).click();

    await expect(authedPage.getByText(/You cannot delete this product category/)).toBeVisible();
    await expect(categoryHeading(authedPage, defaultCategory.name)).toBeVisible();
  });
});
