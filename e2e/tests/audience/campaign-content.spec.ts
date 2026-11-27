import { fileURLToPath } from 'node:url';
import { test, expect } from '../../fixtures';
import { createLiveEventWithFreeTicket } from '../../api/factory';
import { AudiencePage } from '../../pages/audience.page';
import { uniqueEmail, uniqueShort } from '../../utils/unique';
import { buildAudience, createOptedInOrder, waitForContact } from './audience.shared';

const PHOTO = fileURLToPath(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

test.describe('audience campaign content', () => {
  test('a campaign starts from a starter, shows its event as a card, previews as a contact and is saved as a template and duplicated', async ({ freshAccount, publicApi }) => {
    const { api, organizerId } = freshAccount;
    const event = await createLiveEventWithFreeTicket(api, organizerId);
    const lastName = uniqueShort('Fan');
    const fan = uniqueEmail('fan');
    await createOptedInOrder(publicApi, event, { email: fan, firstName: 'Fay', lastName });
    await buildAudience(api, organizerId);
    await waitForContact(api, organizerId, fan);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.gotoCampaigns();
    await audience.campaignCreateButton().click();
    await page.getByTestId('campaign-picker-starter-event_announcement').click();
    await expect(page.getByTitle('Template preview')).toBeVisible();
    await page.getByTestId('campaign-picker-start').click();

    await expect(page).toHaveURL(/\/audience\/campaigns\/new\?starter=event_announcement/);
    await expect(page.getByTestId('campaign-subject')).toHaveValue('Something new from {{ organizer_name }}');
    await expect(page.getByTestId('campaign-preheader')).toHaveValue('Tickets are on sale now.');

    await page.getByTestId('campaign-event').click();
    await page.getByRole('option', { name: event.title }).click();
    const preview = page.getByTestId('campaign-preview').contentFrame();
    await expect(preview.getByText('Get tickets')).toBeVisible();

    await page.getByTestId('campaign-early-access').click();
    await page.getByRole('option', { name: event.productTitle }).click();
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('campaign-early-access-notes')).toContainText('will be hidden from your event page when you send');
    await expect(page.getByTestId('campaign-early-access-notes')).toContainText('The link can be forwarded.');
    await expect(preview.getByText('Get early access')).toBeVisible();

    await page.getByRole('button', { name: 'Insert image' }).click();
    const imageDialog = page.getByRole('dialog', { name: 'Insert Image' });
    const chooser = page.waitForEvent('filechooser');
    await imageDialog.getByRole('button', { name: 'Upload Image' }).click();
    await (await chooser).setFiles(PHOTO);
    await imageDialog.getByTestId('editor-image-alt').fill('The stage before doors open');
    await imageDialog.getByRole('button', { name: 'Insert Image' }).click();
    await expect(page.getByTestId('campaign-body').getByRole('img', { name: 'The stage before doors open' })).toBeVisible();

    await page.getByTestId('campaign-preview-as').click();
    await page.getByTestId('campaign-preview-as').fill(lastName);
    await page.getByRole('option', { name: new RegExp(lastName) }).click();
    await expect(preview.getByText('Hi Fay,')).toBeVisible();

    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    const originalUrl = page.url();

    await page.getByTestId('campaign-more-actions').click();
    await page.getByTestId('campaign-save-template').click();
    await page.getByTestId('template-name').fill(`Announcement ${lastName}`);
    await page.getByTestId('template-save').click();
    await expect(page.getByText('Template saved')).toBeVisible();

    await page.getByTestId('campaign-more-actions').click();
    await page.getByTestId('campaign-duplicate').click();
    await expect(page.getByText('Campaign duplicated')).toBeVisible();
    await expect.poll(() => page.url()).not.toBe(originalUrl);
    await expect(page.getByTestId('campaign-subject')).toHaveValue('Something new from {{ organizer_name }}');

    await page.goto(`/manage/organizer/${organizerId}/audience/templates`);
    const template = page.getByTestId('template-row').filter({ hasText: `Announcement ${lastName}` });
    await expect(template).toBeVisible();
    await template.getByTestId('template-use').click();
    await expect(page.getByTestId('campaign-preheader')).toHaveValue('Tickets are on sale now.');
  });

  test('placeholders are inserted from a menu and filled in the preview, and one we do not know stops the draft being saved', async ({ freshAccount }) => {
    const { api, organizerId } = freshAccount;
    await buildAudience(api, organizerId);
    const organizer = await api.getOrganizer(organizerId);

    const page = await freshAccount.newAuthedPage();
    const audience = new AudiencePage(page, organizerId);
    await audience.composeCampaign('', 'Hello and welcome.');
    const subject = page.getByTestId('campaign-subject');
    await subject.pressSequentially('News from ');
    await page.locator('div').filter({ has: subject }).last().getByRole('button', { name: 'Insert', exact: true }).click();
    await expect(page.getByText('Personalise with')).toBeVisible();
    await expect(page.getByRole('menuitem', { name: /\{\{ event_(title|date|url) \}\}/ })).toHaveCount(0);
    await expect(page.getByRole('menuitem', { name: /first_name/ })).toBeVisible();
    await page.getByRole('menuitem', { name: /organizer_name/ }).click();
    await expect(subject).toHaveValue('News from {{ organizer_name }}');
    await expect(page.getByTestId('campaign-preview-inbox')).toContainText(`News from ${organizer.name}`);
    await expect(page.getByTestId('campaign-from')).toContainText(`Sent on behalf of ${organizer.name}.`);

    await subject.fill('News for {{ favourite_colour }}');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.locator('#campaign-subject-error')).toContainText('favourite_colour');
    await expect(page).toHaveURL(/\/audience\/campaigns\/new/);

    await subject.fill('News from {{ organizer_name }}');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await page.waitForURL(/\/audience\/campaigns\/\d+$/);
    await expect(audience.campaignStatus()).toContainText('Draft');
  });
});
