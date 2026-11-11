const { test, before, after } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs/promises');
const path = require('node:path');
const { chromium } = require('playwright');

const baseURL = process.env.SHOP_URL || 'http://localhost:8080/';
const artifacts = process.env.ARTIFACT_DIR || 'test-results';
let browser;

before(async () => {
  await fs.mkdir(artifacts, { recursive: true });
  browser = await chromium.launch({
    ...(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}),
    args: ['--no-sandbox'],
  });
});
after(async () => { if (browser) await browser.close(); });

async function openMobileMenu(page) {
  const hummingbird = page.locator('[data-bs-target="#mobileMenu"]');
  if (await hummingbird.count()) {
    await hummingbird.click();
    await page.locator('#mobileMenu.show').waitFor();
  } else {
    await page.locator('#menu-icon').click();
    await page.locator('#mobile_top_menu_wrapper').waitFor({ state: 'visible' });
  }
}

async function checkMenu(page) {
  const switcher = page.locator('.store-switcher[data-multistoreswitcher-responsive]');
  assert.equal(await switcher.count(), 1, 'exactly one navigation shop selector');
  assert.equal(await switcher.isVisible(), true, 'shop selector is visible');
  const nativeSelect = switcher.locator('select');
  if (await nativeSelect.isVisible()) {
    assert.ok(await nativeSelect.locator('option').count() >= 2, 'at least two countries available');
    const box = await nativeSelect.boundingBox();
    const viewport = page.viewportSize();
    assert.ok(box.x >= 0 && box.x + box.width <= viewport.width + 1);
    assert.ok(box.y >= 0 && box.y + box.height <= viewport.height + 1);
    return switcher;
  }
  await switcher.locator('button').click();
  await switcher.locator('.dropdown-menu.show').waitFor();
  // Wait for the offcanvas animation and Popper placement to settle.
  await page.waitForFunction(() => Array.from(document.querySelectorAll('.store-switcher .dropdown-menu.show a')).every(link => {
    const box = link.getBoundingClientRect();
    return box.x >= 0 && box.right <= innerWidth + 1 && box.y >= 0 && box.bottom <= innerHeight + 1;
  }), null, { timeout: 5000 });
  const links = switcher.locator('a');
  assert.ok(await links.count() >= 2, 'at least two shops available');
  const viewport = page.viewportSize();
  for (const link of await links.all()) {
    assert.equal(await link.isVisible(), true);
    const box = await link.boundingBox();
    assert.ok(box.x >= 0 && box.x + box.width <= viewport.width + 1, 'shop link fits horizontally');
    assert.ok(box.y >= 0 && box.y + box.height <= viewport.height + 1, 'shop link fits vertically');
  }
  return switcher;
}

for (const width of [768, 1440]) {
  test(`desktop ${width}px keeps shop and language selectors usable`, async () => {
    const page = await browser.newPage({ viewport: { width, height: 1000 } });
    try {
      await page.goto(baseURL, { waitUntil: 'networkidle' });
      assert.equal(await page.locator('#_desktop_language_selector').isVisible(), true);
      await checkMenu(page);
      await page.screenshot({ path: path.join(artifacts, `desktop-${width}.png`) });
    } finally { await page.close(); }
  });
}

for (const width of [320, 390, 767]) {
  test(`mobile ${width}px shows countries and languages and switches shop`, async () => {
    const page = await browser.newPage({ viewport: { width, height: 844 }, isMobile: true, hasTouch: true });
    try {
      await page.goto(baseURL, { waitUntil: 'networkidle' });
      await openMobileMenu(page);
      const language = page.locator('#_mobile_language_selector');
      assert.equal(await language.isVisible(), true, 'language selector remains visible');
      assert.ok(await language.locator('select option').count() >= 2, 'at least two languages available');
      const switcher = await checkMenu(page);
      await page.screenshot({ path: path.join(artifacts, `mobile-${width}.png`) });
      const next = switcher.locator('a:not(.active)').first();
      const destination = await next.getAttribute('href');
      const name = (await next.textContent()).trim();
      await switcher.locator('select').selectOption(destination);
      await page.waitForURL(url => url.origin === new URL(destination).origin);
      await page.waitForLoadState('networkidle');
      await openMobileMenu(page);
      assert.equal((await page.locator('.store-switcher select option:checked').textContent()).trim(), name);
      assert.equal(await page.locator('#_mobile_language_selector').isVisible(), true);
    } finally { await page.close(); }
  });
}

test('resizing moves one selector between desktop and mobile without losing dropdown behavior', async () => {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  try {
    await page.goto(baseURL, { waitUntil: 'networkidle' });
    for (let cycle = 0; cycle < 2; cycle++) {
      await page.setViewportSize({ width: 390, height: 844 });
      await openMobileMenu(page);
      await checkMenu(page);
      const close = page.locator('#mobileMenu .btn-close');
      if (await close.count()) await close.click();
      else await page.locator('#menu-icon').click();
      await page.setViewportSize({ width: 1440, height: 1000 });
      await page.locator('#_desktop_language_selector').waitFor({ state: 'visible' });
      await checkMenu(page);
      await page.locator('.store-switcher button').click();
      assert.equal(await page.locator('.multistoreswitcher-mobile .store-switcher').count(), 0);
    }
  } finally { await page.close(); }
});

test('mobile language selection works alongside the country selector', async () => {
  const page = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  try {
    await page.goto(baseURL, { waitUntil: 'networkidle' });
    await openMobileMenu(page);
    await page.locator('#_mobile_language_selector select').selectOption({ label: 'Français' });
    await page.waitForFunction(() => document.documentElement.lang.startsWith('fr'));
    await page.waitForLoadState('networkidle');
    await openMobileMenu(page);
    await checkMenu(page);
    assert.equal(await page.locator('#_mobile_language_selector').isVisible(), true);
    await page.screenshot({ path: path.join(artifacts, 'mobile-french.png') });
  } finally { await page.close(); }
});
