const { AxePuppeteer } = require('@axe-core/puppeteer');
const puppeteer = require('puppeteer');

(async () => {
  // const browser = await puppeteer.launch();
  const browser = await puppeteer.launch({args: ['--no-sandbox', '--disable-setuid-sandbox']});

  const page = await browser.newPage();
  await page.setBypassCSP(true);

  await page.goto('http://va-gov-cms.lndo.site/');

  await page.type('#edit-name', 'axcsd452ksey');
  await page.type('#edit-pass', 'drupal8');
  await page.click('#edit-submit');
  console.log('clicked submit');
  await page.waitForNavigation();
  console.log('waiting for nav');
  
  // Get cookies
  const cookies = await page.cookies();
  console.log('have cookies');

  // Use cookies in other tab or browser
  const page2 = await browser.newPage();
  await page2.setCookie(...cookies);
  await page2.goto('http://va-gov-cms.lndo.site/node/add/page'); // Opens page as logged user

  const results = await new AxePuppeteer(page2).withTags(['wcag2a', 'wcag2aa']).analyze();
  console.log(results);

  await page.close();
  await browser.close();
})();

