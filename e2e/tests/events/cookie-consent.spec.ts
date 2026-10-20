import { test, expect } from '../../fixtures';
import { PublicEventPage } from '../../pages/public-event.page';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { CONSENT_COOKIE, noStoredConsent } from '../../utils/consent';

test.use({ storageState: noStoredConsent });

test.describe('cookie consent banner', () => {
  test('a visitor saves category choices and is not asked again', async ({ page, api, account }) => {
    const event = await createLiveEventWithFreeTicket(api, account.organizerId);
    const publicEvent = new PublicEventPage(page);
    await publicEvent.goto(event.eventId, event.slug);

    const banner = page.getByRole('dialog', { name: 'Cookie settings' });
    await expect(banner).toBeVisible();
    await banner.getByTestId('cookie-consent-more-choices').click();

    await expect(banner.getByRole('switch', { name: 'Essential' })).toBeDisabled();
    const advertising = banner.getByRole('switch', { name: 'Advertising' });
    await advertising.click({ force: true });
    await expect(advertising).toBeChecked();
    await expect(banner.getByRole('switch', { name: 'Analytics' })).not.toBeChecked();

    await banner.getByTestId('cookie-consent-save').click();
    await expect(banner).toBeHidden();

    const consent = (await page.context().cookies()).find((cookie) => cookie.name === CONSENT_COOKIE);
    expect(consent?.value).toBe('analytics=0&advertising=1');

    await publicEvent.goto(event.eventId, event.slug);
    await expect(page.getByText(event.title).first()).toBeVisible();
    await expect(banner).toBeHidden();

    await page.getByRole('button', { name: 'Cookie settings' }).click();
    await expect(banner).toBeVisible();
    await expect(banner.getByRole('switch', { name: 'Advertising' })).toBeChecked();
    await expect(banner.getByRole('switch', { name: 'Analytics' })).not.toBeChecked();
  });
});
