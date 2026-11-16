/*
 * Responsive and automated accessibility audit (Playwright + axe-core).
 * Usage: node tests/browser/responsive-a11y.cjs http://127.0.0.1:8000 [output.json]
 * Requires the synthetic demo data from `php artisan boba:demo:seed`.
 * Automated checks do not replace manual keyboard and screen-reader review.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const axeSource = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

const BASE = process.argv[2] || 'http://127.0.0.1:8000';
const OUT = process.argv[3] || 'docs/evidence/responsive-a11y.json';
const WIDTHS = [320, 375, 768, 1024, 1440];
const PASSWORD = 'REMOVED-DEMO-PASSWORD';

const journeys = [
  { role: null, pages: ['/', '/about', '/history', '/leadership', '/membership', '/membership/apply', '/membership/status', '/events', '/news', '/contact', '/privacy', '/login', '/account/recover'] },
  { role: 'applicant@example.test', pages: ['/portal', '/portal/profile', '/portal/finances', '/portal/finances/statement', '/portal/notices', '/portal/events', '/portal/security'] },
  { role: 'membership@example.test', pages: ['/admin', '/admin/applications?status=all', '/admin/members', 'FIRST:/admin/applications/', 'FIRST:/admin/members/'] },
  { role: 'treasurer@example.test', pages: ['/admin/finance', '/admin/finance/payments', 'FIRST:/admin/finance/payments/', '/admin/finance/assessments/create', '/admin/finance/reports', 'FIRST:/admin/finance/members/', '/admin/finance/categories'] },
  { role: 'secretariat@example.test', pages: ['/admin/imports/members', 'FIRST:/admin/imports/members/', '/admin/content', '/admin/events', '/admin/enquiries'] },
  { role: 'admin@example.test', pages: ['/admin/access', '/admin/settings', '/admin/audit', '/admin/backups'] },
];

async function resolveFirst(page, prefix) {
  const listing = prefix.replace(/\/$/, '').replace(/\/members$/, '/members').replace(/\/finance\/members$/, '/finance/reports');
  await page.goto(BASE + listing, { waitUntil: 'domcontentloaded' });
  const href = await page.evaluate((p) => {
    const a = [...document.querySelectorAll('main a[href]')].map((x) => new URL(x.href).pathname).find((h) => h.startsWith(p) && /\d+$/.test(h));
    return a || null;
  }, prefix);
  return href;
}

(async () => {
  const browser = await chromium.launch();
  const results = [];
  const consoleErrors = [];
  for (const journey of journeys) {
    const context = await browser.newContext({ bypassCSP: true }); // only so axe-core can be injected; the app itself never needs inline script
    const page = await context.newPage();
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push({ url: page.url(), text: m.text() }); });
    if (journey.role) {
      await page.goto(BASE + '/login');
      await page.fill('#f-email', journey.role);
      await page.fill('#f-password', PASSWORD);
      await Promise.all([page.waitForNavigation(), page.click('main form button[type=submit]')]);
    }
    for (let target of journey.pages) {
      if (target.startsWith('FIRST:')) {
        target = await resolveFirst(page, target.slice(6));
        if (!target) { results.push({ role: journey.role, page: 'unresolved', error: 'no record link found' }); continue; }
      }
      for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 900 });
        const response = await page.goto(BASE + target, { waitUntil: 'networkidle' });
        await page.addScriptTag({ content: axeSource });
        const audit = await page.evaluate(async () => {
          const r = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } });
          return {
            violations: r.violations.map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, sample: v.nodes[0]?.target?.join(' ') })),
            overflow: document.documentElement.scrollWidth - window.innerWidth,
            h1: document.querySelectorAll('h1').length,
            unlabeled: [...document.querySelectorAll('input:not([type=hidden]),select,textarea')].filter((el) => !el.labels?.length && !el.getAttribute('aria-label') && !el.closest('.hp-field') && !el.hidden).length,
          };
        });
        results.push({ role: journey.role || 'visitor', page: target, width, status: response.status(), ...audit });
      }
    }
    await context.close();
  }

  // Keyboard: mobile menu opens with Enter, closes with Escape and returns focus.
  const ctx = await browser.newContext({ viewport: { width: 375, height: 800 } });
  const p = await ctx.newPage();
  await p.goto(BASE + '/');
  await p.focus('details.mobile-menu summary');
  await p.keyboard.press('Enter');
  const opened = await p.evaluate(() => document.querySelector('details.mobile-menu').open);
  await p.keyboard.press('Tab');
  await p.keyboard.press('Escape');
  const closed = await p.evaluate(() => !document.querySelector('details.mobile-menu').open && document.activeElement.tagName === 'SUMMARY');
  // Skip link becomes visible and moves focus to main content.
  await p.goto(BASE + '/');
  await p.keyboard.press('Tab');
  const skipVisible = await p.evaluate(() => document.activeElement.classList.contains('skip-link') && document.activeElement.getBoundingClientRect().top >= 0);
  // Focus indicator is visible on a focused link.
  const focusRing = await p.evaluate(() => getComputedStyle(document.activeElement).boxShadow !== 'none');
  await ctx.close();
  await browser.close();

  const failures = results.filter((r) => r.error || r.status !== 200 || r.overflow > 0 || r.h1 !== 1 || r.unlabeled > 0 || (r.violations || []).length);
  const summary = {
    executed_at: new Date().toISOString(), base: BASE, widths: WIDTHS, page_views: results.length,
    failures: failures.length, console_errors: consoleErrors.length,
    keyboard: { mobile_menu_opens_with_enter: opened, escape_closes_and_returns_focus: closed, skip_link_visible_on_focus: skipVisible, focus_ring_visible: focusRing },
  };
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify({ summary, failures, consoleErrors, results }, null, 2));
  console.log(JSON.stringify(summary, null, 2));
  if (failures.length) console.log(JSON.stringify(failures.slice(0, 15), null, 2));
  process.exit(failures.length || consoleErrors.length ? 1 : 0);
})();
