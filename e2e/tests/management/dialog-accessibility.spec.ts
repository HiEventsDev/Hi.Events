import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import type { FreshAccount } from '../../fixtures';
import { createDraftEvent, createLiveEventWithFreeTicket } from '../../api/factory';
import { EnterpriseSsoSettings } from '../../pages/enterprise-sso.page';
import { enableFeatureFlag } from '../../utils/db';
import { uniqueCode, uniqueEmail, uniqueName } from '../../utils/unique';

const expectFocusInside = async (page: Page, dialog: Locator) => {
  const handle = await dialog.elementHandle();
  await expect.poll(() => page.evaluate((element) => !!element && element.contains(document.activeElement), handle)).toBe(true);
};

const chooseFromMenu = async (page: Page, menuButton: Locator, item: string) => {
  await menuButton.focus();
  await page.keyboard.press('Enter');
  const menuItem = page.getByRole('menuitem', { name: item });
  await expect(menuItem).toBeVisible();
  await menuItem.focus();
  await page.keyboard.press('Enter');
};

const openLineupProfile = async (account: FreshAccount, page: Page) => {
  const event = await createLiveEventWithFreeTicket(account.api, account.organizerId);
  const name = uniqueName('Drawer Focus');
  const profile = await account.api.createLineupProfile(account.organizerId, { name });
  const entry = await account.api.addLineupEntry(event.eventId, { profile_id: profile.id, billing_tier: 'STANDARD' });

  await page.goto(`/manage/event/${event.eventId}/lineup`);
  const trigger = page.getByTestId(`lineup-entry-${entry.id}`).getByRole('button', { name: new RegExp(name) }).filter({ hasText: name });
  await trigger.focus();
  await page.keyboard.press('Enter');
  await expect(page.getByTestId('lineup-profile-save-button')).toBeVisible();

  return { name, trigger };
};

test.describe('shared modal and drawer accessibility', () => {
  test('a create modal names its close button and gives focus back to the button that opened it', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await page.goto('/settings/team');

    const invite = page.getByTestId('team-invite-button');
    await invite.focus();
    await page.keyboard.press('Enter');

    const dialog = page.getByRole('dialog', { name: 'Invite a team member' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Close', exact: true })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(invite).toBeFocused();
  });

  for (const width of [390, 1440]) {
    test(`at ${width}px a dialog opened from a row menu gives focus back to the menu button`, async ({ freshAccount }) => {
      const page = await freshAccount.newAuthedPage();
      await page.setViewportSize({ width, height: 900 });
      const event = await createDraftEvent(freshAccount.api, freshAccount.organizerId);
      const code = uniqueCode('FOCUS');
      await freshAccount.api.createPromoCode(event.eventId, { code, discount_type: 'PERCENTAGE', discount: 10 });

      await page.goto('/settings/team');
      const memberMenu = page.getByTestId('team-member-actions').first();
      await chooseFromMenu(page, memberMenu, 'Edit user');
      const editMember = page.getByRole('dialog', { name: 'Edit member' });
      await expect(editMember).toBeVisible();
      await expectFocusInside(page, editMember);
      await page.keyboard.press('Escape');
      await expect(editMember).toBeHidden();
      await expect(memberMenu).toBeFocused();

      await page.goto(`/manage/event/${event.eventId}/promo-codes`);
      const promoMenu = page.getByRole('row').filter({ hasText: code.toUpperCase() }).getByTestId('promo-code-actions-button');
      await chooseFromMenu(page, promoMenu, 'Edit Code');
      const editCode = page.getByRole('dialog', { name: 'Edit Promo Code' });
      await expect(editCode).toBeVisible();
      await expectFocusInside(page, editCode);
      await page.keyboard.press('Escape');
      await expect(editCode).toBeHidden();
      await expect(promoMenu).toBeFocused();
    });
  }

  test('a side drawer is named by its heading and gives focus back to the row that opened it', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    const { name, trigger } = await openLineupProfile(freshAccount, page);

    const drawer = page.getByRole('dialog', { name });
    await expect(drawer).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
    await expect(trigger).toBeFocused();
  });

  test('on a phone, a long drawer keeps Close in view and its footer above field icons', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await page.setViewportSize({ width: 390, height: 640 });
    await openLineupProfile(freshAccount, page);

    const drawer = page.getByRole('dialog').filter({ has: page.getByTestId('lineup-profile-save-button') });
    await drawer.evaluate((element) => { element.scrollTop = element.scrollHeight; });
    await expect(drawer.getByRole('button', { name: 'Close', exact: true })).toBeInViewport();

    const cancel = drawer.getByRole('button', { name: 'Cancel', exact: true });
    const linkIcon = drawer.getByRole('textbox', { name: 'Link 1' }).locator('xpath=..').locator('[data-position="left"]');
    const iconBox = await linkIcon.boundingBox();
    const cancelBox = await cancel.boundingBox();
    expect(iconBox && cancelBox).toBeTruthy();
    await drawer.evaluate((element, delta) => { element.scrollTop += delta; }, (iconBox!.y + iconBox!.height / 2) - (cancelBox!.y + cancelBox!.height / 2));

    const stacking = await cancel.evaluate((button, icon) => {
      if (!icon) {
        return null;
      }
      const box = button.getBoundingClientRect();
      const iconRect = icon.getBoundingClientRect();
      const iconCentreY = iconRect.top + iconRect.height / 2;
      const hit = document.elementFromPoint(iconRect.left + iconRect.width / 2, box.top + box.height / 2);
      return {
        iconUnderCancel: iconCentreY > box.top && iconCentreY < box.bottom,
        cancelOnTop: !!hit && button.contains(hit),
      };
    }, await linkIcon.elementHandle());
    expect(stacking).toEqual({ iconUnderCancel: true, cancelOnTop: true });
  });

  test('the single sign-on drawer is named and its copy button has a name', async ({ freshAccount }) => {
    const { id } = await freshAccount.api.getAccount();
    enableFeatureFlag(id, 'enterprise_sso');
    const page = await freshAccount.newAuthedPage();
    const settings = new EnterpriseSsoSettings(page);
    await settings.open();

    const drawer = page.getByRole('dialog', { name: 'Single sign-on' });
    await expect(drawer).toBeVisible();

    await page.getByTestId('sso-provider-card-OIDC').click();
    await expect(drawer.getByRole('button', { name: 'Copy redirect URI', exact: true })).toBeVisible();
  });

  test('saving settings with the keyboard keeps focus on the save button', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await page.goto('/settings/profile');

    const firstName = page.getByRole('textbox', { name: 'First name' });
    await expect(firstName).toHaveValue('E2E');
    await firstName.fill('Keyboard');

    const save = page.getByTestId('profile-save-button');
    await save.focus();
    await page.keyboard.press('Enter');

    await expect(page.getByText('Profile updated successfully')).toBeVisible();
    await expect(save).toBeFocused();
    await expect(save).toBeDisabled();
  });

  test('a failed save inside the confirm-identity dialog does not move focus behind the dialog', async ({ freshAccount }) => {
    const page = await freshAccount.newAuthedPage();
    await page.goto('/settings/profile');

    await expect(page.getByRole('textbox', { name: 'First name' })).toHaveValue('E2E');
    await page.locator('#profile-email').fill(uniqueEmail('focus'));

    const save = page.getByTestId('profile-save-button');
    await save.focus();
    await page.keyboard.press('Enter');

    const dialog = page.getByRole('dialog', { name: "Confirm it's you" });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Current password').fill('definitely-wrong-password');

    const confirm = dialog.getByTestId('confirm-identity-submit');
    await confirm.focus();
    await page.keyboard.press('Enter');

    await expect(dialog.getByText('The password is incorrect.')).toBeVisible();
    await expect(confirm).toBeEnabled();
    await expect(dialog).toBeVisible();
    await expect(save).not.toBeFocused();
    const focusOutsideDialog = await page.evaluate(() => {
      const active = document.activeElement;
      return !!active && active !== document.body && !active.closest('[role="dialog"]');
    });
    expect(focusOutsideDialog).toBe(false);
  });
});
