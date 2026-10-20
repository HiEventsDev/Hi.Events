import { fileURLToPath } from 'node:url';
import { test, expect } from '../../fixtures';
import { createFreshOrganizer, createLiveEventWithProduct } from '../../api/factory';
import { DesignerPage } from '../../pages/designer.page';
import { OrganizerPublicPage } from '../../pages/organizer.page';
import { uniqueName } from '../../utils/unique';

const GALLERY_PHOTO = fileURLToPath(new URL('../../fixtures/assets/gallery-photo.png', import.meta.url));

test.describe('organizer homepage sections', () => {
  test('an organizer adds custom sections and hides built-ins on their public page', async ({ authedPage: page, api }) => {
    const organizer = await createFreshOrganizer(api, uniqueName('E2E Sections Org'));
    await api.updateOrganizerStatus(organizer.id, 'LIVE');
    const event = await createLiveEventWithProduct(api, { organizerId: organizer.id });

    const publicPage = new OrganizerPublicPage(page);
    await publicPage.goto(organizer.id, organizer.slug);
    await expect(page.getByRole('button', { name: 'Past', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Contact' })).toBeVisible();

    const designer = new DesignerPage(page);
    await designer.gotoOrganizerHomepageDesigner(organizer.id);
    await designer.toggleSection('past_events');
    await designer.toggleSection('contact');

    const text = await designer.addSection('text');
    await text.getByLabel('Heading').fill('Our story');
    await text.locator('[contenteditable="true"]').fill('We have run community gigs since 2010.');

    const gallery = await designer.addSection('gallery');
    await gallery.locator('input[type="file"]').setInputFiles(GALLERY_PHOTO);
    await expect(gallery.locator('img')).toHaveCount(1);

    await designer.saveButton.click();
    await expect(designer.savedStatus).toBeVisible();

    await publicPage.goto(organizer.id, organizer.slug);

    await expect(publicPage.eventLink(event.title)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Past', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Contact' })).toHaveCount(0);
    await expect(page.getByTestId('homepage-section-text')).toContainText('Our story');
    await expect(page.getByTestId('homepage-section-text')).toContainText('community gigs since 2010');

    await page.getByTestId('homepage-section-gallery').getByRole('button', { name: 'Open photo 1' }).click();
    await expect(page.getByRole('button', { name: 'Close' })).toBeVisible();
  });
});
