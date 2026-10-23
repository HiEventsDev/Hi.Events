import type { Browser } from '@playwright/test';

export interface PosterArt {
  width: number;
  height: number;
  title: string;
  background: string;
  ink: string;
}

export async function renderPosterArt(browser: Browser, art: PosterArt): Promise<Buffer> {
  const page = await browser.newPage({ viewport: { width: art.width, height: art.height } });
  const unit = Math.min(art.width, art.height) / 100;
  await page.setContent(`
    <body style="margin:0">
      <div style="box-sizing:border-box;width:${art.width}px;height:${art.height}px;display:flex;flex-direction:column;justify-content:space-between;padding:${unit * 7}px;border:${unit * 1.4}px solid ${art.ink};background:${art.background};color:${art.ink};font-family:Georgia,serif">
        <div style="font:700 ${unit * 3.2}px/1.2 Arial,sans-serif;letter-spacing:.24em;text-transform:uppercase">Harbour Playhouse presents</div>
        <div style="font-size:${unit * 13}px;line-height:.95">${art.title}</div>
        <div style="font:700 ${unit * 3.2}px/1.2 Arial,sans-serif;letter-spacing:.16em;text-transform:uppercase">Saturday 14 November · Doors 7pm</div>
      </div>
    </body>`);
  const buffer = await page.screenshot({ type: 'jpeg', quality: 82 });
  await page.close();
  return buffer;
}
