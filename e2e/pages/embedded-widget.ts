import { type Frame, type Locator, type Page } from '@playwright/test';

export const EMBED_HOST_URL = 'http://localhost:59173/';

const WIDGET_IFRAME_SELECTOR = 'iframe[title="Hi.Events Widget"]';
const CHECKOUT_IFRAME_SELECTOR = 'iframe[title="Hi.Events Checkout"]';

async function resolveFrame(page: Page, selector: string): Promise<Frame> {
  const iframe = page.locator(selector);
  await iframe.waitFor({ state: 'attached', timeout: 20_000 });
  const handle = await iframe.elementHandle();
  const frame = handle ? await handle.contentFrame() : null;
  if (!frame) {
    throw new Error(`No content frame found for ${selector}`);
  }
  return frame;
}

export async function openEmbeddedWidget(
  page: Page,
  baseURL: string,
  eventId: number,
  attributes: Record<string, string> = {},
): Promise<Frame> {
  const extraAttributes = Object.entries(attributes)
    .map(([name, value]) => `data-hievents-${name}="${value}"`)
    .join(' ');

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Embed Host</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script async src="${baseURL}/widget.js"></script>
</head>
<body>
  <h1>Third-party host page</h1>
  <div class="hievents-widget" data-hievents-id="${eventId}" ${extraAttributes}></div>
  <p>Host page footer content</p>
</body>
</html>`;

  await page.context().grantPermissions(['local-network-access'], { origin: new URL(EMBED_HOST_URL).origin });
  await page.route(`${EMBED_HOST_URL}**`, (route) => {
    if (route.request().url() === EMBED_HOST_URL) {
      return route.fulfill({ contentType: 'text/html', body: html });
    }
    return route.fulfill({ status: 404, body: '' });
  });

  await page.goto(EMBED_HOST_URL);

  return resolveFrame(page, WIDGET_IFRAME_SELECTOR);
}

export function widgetIframe(page: Page): Locator {
  return page.locator(WIDGET_IFRAME_SELECTOR);
}

export function checkoutModal(page: Page): Locator {
  return page.locator('#hievents-checkout-modal');
}

export function checkoutModalDialog(page: Page): Locator {
  return page.getByRole('dialog', { name: 'Checkout' });
}

export function resolveCheckoutFrame(page: Page): Promise<Frame> {
  return resolveFrame(page, CHECKOUT_IFRAME_SELECTOR);
}

export async function openModalCheckout(page: Page, widgetFrame: Frame): Promise<Frame> {
  await widgetFrame.getByTestId('checkout-continue-button').click();
  const checkoutFrame = await resolveCheckoutFrame(page);
  await checkoutFrame.waitForURL(/\/checkout\/\d+\/[^/]+\/details/);
  return checkoutFrame;
}
