import { test, expect } from '../../fixtures';
import { ShareModalPage } from '../../pages/share-modal.page';
import { createDraftEvent } from '../../api/factory';

test.describe('sharing an event', () => {
  test('an event without a cover image previews as a text card and prompts for a cover', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);

    const share = new ShareModalPage(authedPage);
    await share.open(event.eventId);

    await expect(share.linkInput()).toHaveValue(new RegExp(`/event/${event.eventId}/`));
    await expect(share.previewCard()).toContainText(event.title);
    await expect(share.previewImage()).toHaveCount(0);

    await share.captionInput().fill('Doors open at seven');
    await expect(share.dialog().getByText('Doors open at seven', { exact: true }).first()).toBeVisible();

    await expect(share.addCoverPrompt()).toContainText('This event has no cover image');
    await share.addCoverPrompt().getByRole('link', { name: 'Add a cover image' }).click();
    await expect(authedPage).toHaveURL(new RegExp(`/manage/event/${event.eventId}/homepage-designer`));
  });

  test('picking a suggested caption fills the caption and the preview', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const share = new ShareModalPage(authedPage);
    await share.stubSuggestions({
      availability: 'AVAILABLE',
      captions: [
        { tone: 'FRIENDLY', text: 'Come along on Friday and bring a friend' },
        { tone: 'SHORT', text: 'Friday night, doors at seven' },
      ],
      remaining_generations: 12,
    });

    await share.open(event.eventId);

    await expect(share.dialog().getByText('12 left today')).toBeVisible();
    await expect(share.suggestCaptionsButton()).toContainText('Suggest new ones');

    await share.suggestion('short').click();

    await expect(share.captionInput()).toHaveValue('Friday night, doors at seven');
    await expect(share.suggestion('short')).toHaveAttribute('aria-pressed', 'true');
    await expect(share.suggestion('friendly')).toHaveAttribute('aria-pressed', 'false');
  });

  test('an account that is not yet trusted is pointed at payouts instead of suggestions', async ({ authedPage, api, account }) => {
    const event = await createDraftEvent(api, account.organizerId);
    const share = new ShareModalPage(authedPage);
    await share.stubSuggestions({
      availability: 'PAYMENTS_NOT_CONNECTED',
      captions: [],
      remaining_generations: 20,
    });

    await share.open(event.eventId);

    await expect(authedPage.getByTestId('share-suggestions-connect-hint')).toContainText('once you connect Stripe');
    await expect(share.suggestCaptionsButton()).toHaveCount(0);
  });
});
