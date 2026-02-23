const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();
  await page.goto('http://localhost:5678/event/1/aurora-digitalis-il-risveglio-dei-sensi', { waitUntil: 'networkidle2' });
  
  const selectors = await page.evaluate(() => {
    const rows = Array.from(document.querySelectorAll('.hi-product-quantity-selector'));
    return rows.map(r => ({
      visible: r.offsetWidth > 0 || r.offsetHeight > 0,
      parentText: r.closest('.flex.items-center.justify-between')?.innerText
    }));
  });
  
  console.log(JSON.stringify(selectors, null, 2));
  await browser.close();
})();
