import { type Frame, type Page } from '@playwright/test';
import { test, expect } from '../../fixtures';
import { createDraftEventWithTicket } from '../../api/factory';
import { DesignerPage } from '../../pages/designer.page';
import { waitForHydration } from '../../utils/hydration';
import { uniqueName } from '../../utils/unique';

const FOREIGN_PAGE_URL = 'http://localhost:59174/';
const INJECTED_HEADING = 'Injected preview heading';

type ProbeWindow = Window & {
  injectionSeen?: boolean;
  previewScriptRan?: boolean;
  previewTarget?: Window | null;
};

const injectedSettings = {
  homepage_sections: [
    {
      id: 'injected-text',
      type: 'TEXT',
      enabled: true,
      heading: INJECTED_HEADING,
      body: '<img src="data:," onerror="window.previewScriptRan = true">',
    },
  ],
};

const watchForInjection = (page: Page) => page.context().addInitScript((heading) => {
  const probe = window as ProbeWindow;
  new MutationObserver(() => {
    if (document.body?.textContent?.includes(heading)) {
      probe.injectionSeen = true;
    }
  }).observe(document, { subtree: true, childList: true, characterData: true });
}, INJECTED_HEADING);

const injectionReached = async (target: Page | Frame): Promise<boolean> => {
  try {
    return await target.evaluate(() => {
      const probe = window as ProbeWindow;
      return probe.injectionSeen === true || probe.previewScriptRan === true;
    });
  } catch {
    return false;
  }
};

const openForeignPage = async (page: Page, body = ''): Promise<void> => {
  const html = `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Foreign page</title></head><body><p>Foreign page</p>${body}</body></html>`;
  await page.context().grantPermissions(['local-network-access'], { origin: new URL(FOREIGN_PAGE_URL).origin });
  await page.route(`${FOREIGN_PAGE_URL}**`, (route) => {
    if (route.request().url().split('?')[0] === FOREIGN_PAGE_URL) {
      return route.fulfill({ contentType: 'text/html', body: html });
    }
    return route.fulfill({ status: 404, body: '' });
  });
  await page.goto(FOREIGN_PAGE_URL);
};

const openPreviewPopup = async (opener: Page, previewUrl: string): Promise<Page> => {
  const [popup] = await Promise.all([
    opener.context().waitForEvent('page'),
    opener.evaluate((url) => {
      (window as ProbeWindow).previewTarget = window.open(url, 'hi-preview');
    }, previewUrl),
  ]);
  await popup.waitForLoadState();
  await waitForHydration(popup, 'h1');
  return popup;
};

const postInjectedSettings = (sender: Page, type: string, durationMs = 2500) =>
  sender.evaluate(async ({ messageType, settings, duration }) => {
    const target = (window as ProbeWindow).previewTarget!;
    const deadline = Date.now() + duration;
    while (Date.now() < deadline) {
      target.postMessage({ type: messageType, settings }, '*');
      await new Promise((resolve) => setTimeout(resolve, 250));
    }
  }, { messageType: type, settings: injectedSettings, duration: durationMs });

test.describe('homepage preview messages', () => {
  test('the event preview ignores settings posted by a foreign page that opened it', async ({ authedPage: page, api, account, baseURL }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    await watchForInjection(page);
    await openForeignPage(page);

    const preview = await openPreviewPopup(page, `${baseURL}/event/${event.eventId}/preview`);
    await expect(preview.getByRole('heading', { name: event.title })).toBeVisible();

    await postInjectedSettings(page, 'UPDATE_SETTINGS');

    expect(await injectionReached(preview)).toBe(false);
    await expect(preview.getByText(INJECTED_HEADING)).toHaveCount(0);
    await expect(preview.getByRole('heading', { name: event.title })).toBeVisible();
  });

  test('the event preview ignores settings from an app window that is not its parent frame', async ({ authedPage: page, api, account, baseURL }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const previewUrl = `${baseURL}/event/${event.eventId}/preview`;
    await watchForInjection(page);
    await page.goto(previewUrl);
    await waitForHydration(page, 'h1');

    const preview = await openPreviewPopup(page, previewUrl);
    await expect(preview.getByRole('heading', { name: event.title })).toBeVisible();

    await postInjectedSettings(page, 'UPDATE_SETTINGS');

    expect(await injectionReached(preview)).toBe(false);
    await expect(preview.getByText(INJECTED_HEADING)).toHaveCount(0);
  });

  test('a foreign page cannot frame the event preview and drive it', async ({ authedPage: page, api, account, baseURL }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    await watchForInjection(page);
    await openForeignPage(page, `<iframe id="preview" title="Framed preview" src="${baseURL}/event/${event.eventId}/preview" style="width:1000px;height:800px"></iframe>`);
    await page.evaluate(() => {
      (window as ProbeWindow).previewTarget = (document.getElementById('preview') as HTMLIFrameElement).contentWindow;
    });

    await postInjectedSettings(page, 'UPDATE_SETTINGS', 8000);

    const frame = (await (await page.locator('#preview').elementHandle())!.contentFrame())!;
    expect(await injectionReached(frame)).toBe(false);
    await expect(frame.getByText(INJECTED_HEADING)).toHaveCount(0);
  });

  test('the organizer preview ignores settings posted by a foreign page that opened it', async ({ authedPage: page, account, baseURL }) => {
    await watchForInjection(page);
    await openForeignPage(page);

    const preview = await openPreviewPopup(page, `${baseURL}/organizer/${account.organizerId}/preview`);
    await postInjectedSettings(page, 'UPDATE_ORGANIZER_SETTINGS');

    expect(await injectionReached(preview)).toBe(false);
    await expect(preview.getByText(INJECTED_HEADING)).toHaveCount(0);
  });

  test('the homepage designers still drive their own previews', async ({ authedPage: page, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);
    const designer = new DesignerPage(page);

    await designer.gotoHomepageDesigner(event.eventId);
    const eventHeading = uniqueName('Event preview heading');
    const eventText = await designer.addSection('text');
    await eventText.getByLabel('Heading').fill(eventHeading);
    await eventText.locator('[contenteditable="true"]').fill('Doors open at seven.');
    await expect(designer.eventPreview.getByTestId('homepage-section-text')).toContainText(eventHeading);

    await designer.gotoOrganizerHomepageDesigner(account.organizerId);
    const organizerHeading = uniqueName('Organizer preview heading');
    const organizerText = await designer.addSection('text');
    await organizerText.getByLabel('Heading').fill(organizerHeading);
    await organizerText.locator('[contenteditable="true"]').fill('Meet the team behind the festival.');
    await expect(page.frameLocator('iframe[title="Organizer Homepage Preview"]').getByTestId('homepage-section-text'))
      .toContainText(organizerHeading);
  });

  test('the preview routes can only be framed by the app itself', async ({ request, api, account }) => {
    const event = await createDraftEventWithTicket(api, account.organizerId);

    for (const path of [
      `/event/${event.eventId}/preview`,
      `/organizer/${account.organizerId}/preview`,
      `/event/${event.eventId}/preview//`,
      `/organizer/${account.organizerId}/preview//`,
      `/Event/${event.eventId}/Preview`,
      `/event/${event.eventId}/%70review`,
      '/Manage/events',
      '/%6Danage/events',
      '/AUTH/LOGIN',
    ]) {
      const response = await request.get(path);
      expect(response.headers()['content-security-policy'], path).toBe("frame-ancestors 'self'");
      expect(response.headers()['x-frame-options'], path).toBe('SAMEORIGIN');
    }
  });
});
