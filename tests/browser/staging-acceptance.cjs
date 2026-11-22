/**
 * Staging acceptance over real HTTP(S): reproduces the seven mandatory
 * workflows and the security checks against a deployed site by driving its
 * actual forms in Chromium. Synthetic data only. Light, sequential traffic
 * (one browser, no load testing) — safe for shared hosting.
 *
 *   node tests/browser/staging-acceptance.cjs <base-url> <links.json> [out.json]
 *
 * <links.json> is written on the server by
 *   php artisan boba:staging:prepare --confirm-synthetic --links-file=...
 * and copied privately to the computer running this script; delete it after.
 *
 * Optional environment:
 *   STAGING_HTTP_USER / STAGING_HTTP_PASS   if the staging protection is cPanel Directory Privacy
 *   STAGING_ALLOW_HTTP=1                    only for the local simulation (http://127.0.0.1)
 *   STAGING_BROWSER_CHANNEL=chrome          use the installed Google Chrome (or msedge) instead of
 *                                           Playwright's Chromium, e.g. where `npx playwright install
 *                                           chromium` is unsupported (macOS 13 with Playwright 1.63)
 *
 * Certificate errors are NEVER ignored: an invalid certificate stops the run.
 * Officer passwords are random, held in memory for this run only, never printed.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const crypto = require('crypto');

const BASE = (process.argv[2] || '').replace(/\/$/, '');
const LINKS = process.argv[3];
const OUT = process.argv[4] || `staging-acceptance-${new Date().toISOString().slice(0, 10)}.json`;
if (!BASE || !LINKS) { console.error('usage: node tests/browser/staging-acceptance.cjs <base-url> <links.json> [out.json]'); process.exit(2); }
if (!BASE.startsWith('https://') && !(process.env.STAGING_ALLOW_HTTP === '1' && /^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(BASE))) {
  console.error('Refusing: staging acceptance must run over HTTPS with a valid certificate.'); process.exit(2);
}
const HTTPS = BASE.startsWith('https://');
const links = JSON.parse(fs.readFileSync(LINKS, 'utf8')).officers;
const RUN = Date.now().toString(36).toUpperCase();
const DOMAIN = 'staging-synthetic.invalid';
const today = new Date(); const iso = (d) => d.toISOString().slice(0, 10);
const daysAgo = (n) => iso(new Date(today.getTime() - n * 86400000));
const phone = () => '080' + String(crypto.randomInt(10000000, 99999999));
const password = () => crypto.randomBytes(18).toString('base64url') + 'Qz7';

const results = [];
const facts = {};
function record(step, ok, detail = '') { results.push({ step, result: ok ? 'pass' : 'FAIL', detail }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${step}${detail ? ' — ' + detail : ''}`); }
async function attempt(step, fn) { try { const d = await fn(); record(step, true, d || ''); return true; } catch (e) { record(step, false, String(e.message || e).split('\n')[0].slice(0, 300)); return false; } }
function must(cond, msg) { if (!cond) throw new Error(msg); }

(async () => {
  const channel = process.env.STAGING_BROWSER_CHANNEL || undefined;
  if (channel && !/^(chrome|chrome-beta|msedge|msedge-beta)$/.test(channel)) { console.error('STAGING_BROWSER_CHANNEL must be chrome, chrome-beta, msedge or msedge-beta'); process.exit(2); }
  const browser = await chromium.launch(channel ? { channel } : {});
  const ctxOptions = { ignoreHTTPSErrors: false };
  if (process.env.STAGING_HTTP_USER) ctxOptions.httpCredentials = { username: process.env.STAGING_HTTP_USER, password: process.env.STAGING_HTTP_PASS || '' };
  const contexts = [];
  async function newPage() {
    const ctx = await browser.newContext(ctxOptions); contexts.push(ctx);
    const page = await ctx.newPage();
    page.on('dialog', (d) => d.accept()); // data-confirm prompts
    page.setDefaultTimeout(30000);
    return page;
  }
  async function go(page, path) {
    const r = await page.goto(BASE + path, { waitUntil: 'load' });
    return r ? r.status() : 0;
  }
  // Fill and submit the form whose action ends with `action` (path), checking for ModSecurity/server blocks.
  async function submit(page, action, fields = {}, button = null) {
    const form = page.locator(`form[action$="${action}"]`).first();
    try { await form.waitFor({ timeout: 15000 }); } catch {
      throw new Error(`no form for ${action} at ${page.url()}: ${(await page.locator('main').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 200)}`);
    }
    for (const [name, value] of Object.entries(fields)) {
      const el = form.locator(`[name="${name}"]`).first();
      const tag = await el.evaluate((n) => n.tagName.toLowerCase() + ':' + (n.type || ''));
      if (tag.startsWith('select')) await el.selectOption(String(value));
      else if (tag.endsWith(':checkbox') || tag.endsWith(':radio')) { if (value) await tick(el); }
      else if (tag.endsWith(':file')) await el.setInputFiles(value);
      else await el.fill(String(value));
    }
    const btn = button ? form.getByRole('button', { name: button }) : form.locator('button[type=submit]').first();
    const [resp] = await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), btn.click()]);
    const status = resp ? resp.status() : 0;
    must(![403, 406, 501, 500, 502, 503].includes(status) || (await page.locator('main').count()) > 0, `blocked or server error (HTTP ${status}) submitting ${action} — check ModSecurity`);
    must(status < 500, `HTTP ${status} after submitting ${action}`);
    return status;
  }
  // Visually styled checkboxes/radios may sit outside the viewport; set them like a click would.
  async function tick(el) {
    try { await el.check({ force: true, timeout: 3000 }); } catch {
      await el.evaluate((n) => { n.checked = true; n.dispatchEvent(new Event('input', { bubbles: true })); n.dispatchEvent(new Event('change', { bubbles: true })); });
    }
  }
  async function errors(page) { return (await page.locator('.field-error, .alert.error, [role=alert].error').allInnerTexts()).join(' | '); }
  async function text(page) { return page.locator('main').innerText(); }
  const p = (path) => new URL(BASE + path).pathname;

  // ---- 0. Transport, headers, cookies -----------------------------------
  const anon = await newPage();
  const ok0 = await attempt('HTTPS, security headers and cookie flags', async () => {
    const assets = [];
    anon.on('response', (res) => { if (res.url().includes('/build/')) assets.push(res.status()); });
    const r = await anon.goto(BASE + '/', { waitUntil: 'load' });
    must(r.status() === 200, `home returned ${r.status()}`);
    must(assets.length >= 2 && assets.every((s) => s === 200), `compiled CSS/JS not served (statuses ${assets.join(',') || 'none'}) — check public/build and .htaccess`);
    must(await anon.evaluate(() => !document.documentElement.classList.contains('no-js')), 'JavaScript did not run (CSP or asset path)');
    const h = r.headers();
    must((h['content-security-policy'] || '').includes("default-src 'self'"), 'CSP missing');
    must(h['x-frame-options'] === 'DENY' && h['x-content-type-options'] === 'nosniff', 'frame/nosniff headers missing');
    if (HTTPS) must((h['strict-transport-security'] || '').includes('max-age'), 'HSTS missing');
    await go(anon, '/login');
    const cookies = await anon.context().cookies();
    const session = cookies.find((c) => /session/i.test(c.name));
    must(session && session.httpOnly && session.sameSite === 'Lax', 'session cookie must be HttpOnly and SameSite=Lax');
    if (HTTPS) must(session.secure, 'session cookie must be Secure');
    return `HTTP 200; ${assets.length} compiled assets served; CSP, X-Frame-Options, nosniff${HTTPS ? ', HSTS' : ''}; session cookie ${HTTPS ? 'Secure; ' : ''}HttpOnly; SameSite=Lax`;
  });
  if (!ok0) { finish(); return; }

  // ---- 1. Synthetic officers: activate via single-use links --------------
  const officers = {};
  for (const [role, link] of Object.entries(links)) {
    await attempt(`Activate synthetic ${role} through its single-use link`, async () => {
      const page = await newPage();
      const pw = password();
      const r = await page.goto(link.url, { waitUntil: 'load' });
      must(!page.url().includes(new URL(link.url).pathname.split('/').pop()), 'token still in the address bar');
      must((r.request().redirectedFrom() ? (await r.request().redirectedFrom().response()).headers()['referrer-policy'] : r.headers()['referrer-policy']) === 'no-referrer', 'Referrer-Policy no-referrer missing on the link');
      await submit(page, link.purpose === 'activation' ? '/account/activate' : '/account/reset', { password: pw, password_confirmation: pw });
      if (p('/login') === new URL(page.url()).pathname) {
        await submit(page, '/login', { email: link.email, password: pw });
      }
      must(new URL(page.url()).pathname.startsWith(p('/admin')), `not signed in to administration (at ${page.url()})`);
      officers[role] = page;
      return `${link.purpose} completed; signed in`;
    });
  }
  const need = (...roles) => roles.every((r) => officers[r]);

  // ---- Role restrictions ---------------------------------------------------
  await attempt('Server-side role restrictions', async () => {
    must(need('system_admin', 'treasurer', 'secretariat'), 'officers missing');
    const s1 = await go(officers.system_admin, '/admin/finance');
    const s2 = await go(officers.treasurer, '/admin/imports/members');
    const s3 = await go(officers.secretariat, '/admin/finance/payments');
    const fresh = await newPage(); await go(fresh, '/admin');
    must(s1 === 403 && s2 === 403 && s3 === 403, `expected 403s, got admin→finance ${s1}, treasurer→imports ${s2}, secretariat→payments ${s3}`);
    must(new URL(fresh.url()).pathname === p('/login'), 'anonymous /admin not sent to sign-in');
    return 'System Administrator→finance 403; Treasurer→member imports 403; Secretariat→payments 403; anonymous→sign-in';
  });

  // ---- W1. Existing-member import ------------------------------------------
  await attempt('W1 Existing-member CSV import (preview, commit, duplicate detection)', async () => {
    must(need('secretariat'), 'secretariat missing');
    const s = officers.secretariat;
    const csv = `full_name,email,phone,date_of_birth,joined_on,ijebu_town\nSynthetic Existing ${RUN} One,existing1-${RUN.toLowerCase()}@${DOMAIN},${phone()},1980-04-02,2012-05-01,Ijebu Ode\nSynthetic Existing ${RUN} Two,existing2-${RUN.toLowerCase()}@${DOMAIN},${phone()},12/11/1982,2013-06-01,Ijebu Igbo\n`;
    await go(s, '/admin/imports/members');
    await submit(s, '/admin/imports/members', { file: { name: `existing-${RUN}.csv`, mimeType: 'text/csv', buffer: Buffer.from(csv) } });
    let t = await text(s);
    must(/Rows\s*2/.test(t) && /Valid\s*2/.test(t), 'preview did not show 2 valid rows: ' + (await errors(s)));
    const batchPath = new URL(s.url()).pathname;
    await submit(s, batchPath.replace(/^.*(\/admin\/imports\/members\/\d+)$/, '$1') + '/commit', { confirm: true });
    must((await text(s)).match(/complet|imported/i), 'commit not confirmed: ' + (await errors(s)));
    await go(s, '/admin/imports/members');
    await submit(s, '/admin/imports/members', { file: { name: `existing-${RUN}-again.csv`, mimeType: 'text/csv', buffer: Buffer.from(csv + '\n') } });
    t = await text(s);
    must(/Duplicates\s*2/.test(t), 're-upload did not flag 2 duplicates');
    await submit(s, new URL(s.url()).pathname.replace(/^.*(\/admin\/imports\/members\/\d+)$/, '$1') + '/cancel');
    facts.import_batch = batchPath;
    return '2 members imported without login accounts; re-upload flagged 2 duplicates and was cancelled';
  });

  // ---- W2. Application, tracking, approval ------------------------------------
  const applicant = { first: 'Synthetic', last: `Applicant ${RUN}`, email: `applicant-${RUN.toLowerCase()}@${DOMAIN}`, phone: phone() };
  await attempt('W2 Membership application, tracking and approval', async () => {
    must(need('membership_initiator', 'membership_officer', 'system_admin'), 'officers missing');
    const a = await newPage();
    await go(a, '/membership/apply');
    await a.waitForTimeout(4500); // public forms reject submissions made within seconds of loading
    // Synthetic supporting documents (a minimal plain PDF and a 1x1 PNG), as the association's form requires.
    const pdf = (name) => ({ name, mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n') });
    const png = (name) => ({ name, mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') });
    await submit(a, '/membership/apply', {
      first_name: applicant.first, other_names: 'Acceptance', last_name: applicant.last, email: applicant.email, phone: applicant.phone,
      date_of_birth_day: '9', date_of_birth_month: '9', date_of_birth_year: '1981', occupation: 'Synthetic accountant', ijebu_town: 'Ago Iwoye',
      city: 'Lagos', country: 'Nigeria', profile_summary: 'Synthetic staging acceptance application for workflow two.',
      'attachments[birth_certificate]': pdf('birth.pdf'), 'attachments[lg_certificate]': pdf('lg-origin.pdf'),
      'attachments[first_school_certificate]': pdf('fslc.pdf'), 'attachments[secondary_school_certificate]': pdf('waec.pdf'),
      'attachments[referee_letter][]': [pdf('referee-1.pdf'), pdf('referee-2.pdf')],
      'attachments[passport_photo]': png('passport.png'), 'attachments[full_photo]': png('full.png'),
      declaration: true, privacy: true });
    const ref = (await a.locator('#ref').innerText()).trim(); const code = (await a.locator('#code').innerText()).trim();
    must(ref && code, 'no receipt reference/code: ' + (await errors(a)));
    await go(a, '/membership/status'); await submit(a, '/membership/status', { reference: ref, code });
    must(/Received/i.test(await text(a)), 'status lookup did not show Received');
    const mo = officers.membership_officer;
    await go(mo, '/admin/applications?status=all&q=' + encodeURIComponent(ref));
    const href = await mo.locator(`tr:has-text("${ref}") a[href*="/admin/applications/"]`).first().getAttribute('href');
    const appPath = new URL(href, BASE).pathname;
    must((await go(officers.system_admin, appPath)) === 403, 'System Administrator could open the application');
    await go(mo, appPath);
    // Two-step approval: the General Secretary (Membership Initiator) reviews and recommends; the chair decides.
    const gs = officers.membership_initiator;
    await go(gs, appPath);
    await submit(gs, appPath + '/decision', { decision: 'under_review', private_note: 'Synthetic staging review' });
    await submit(gs, appPath + '/decision', { decision: 'recommend_approve', private_note: 'Documents and referees checked (synthetic)' });
    must(/Recommend/i.test(await text(gs)) && !(await gs.locator('select[name="decision"] option[value="approved"]').count()), 'the General Secretary was offered the final approval');
    await go(mo, appPath);
    must(/Recommended for approval/i.test(await text(mo)), 'the chair does not see the recommendation');
    await submit(mo, appPath + '/decision', { decision: 'approved', private_note: 'Meets the confirmed criteria (synthetic)' });
    const url = (await mo.locator('#otl-url').innerText()).trim();
    must(url.includes('/account/activate/'), 'no activation link shown after approval: ' + (await errors(mo)));
    facts.application = ref; facts.activation_url = url;
    return `application ${ref} tracked, recommended by the General Secretary role, approved by the chair; one-time activation link shown once`;
  });

  // ---- W3. Member activation --------------------------------------------------
  let member = null; const memberPw = password();
  await attempt('W3 Member account activation and portal', async () => {
    must(facts.activation_url, 'no activation link from W2');
    member = await newPage();
    await member.goto(facts.activation_url, { waitUntil: 'load' });
    await submit(member, '/account/activate', { password: memberPw, password_confirmation: memberPw });
    must(new URL(member.url()).pathname.startsWith(p('/portal')), 'not taken to the member portal');
    facts.membership_number = ((await text(member)).match(/[A-Z]{2,}-\d{3,}/) || [])[0];
    must((await go(member, '/admin')) === 403, 'member reached administration');
    const replay = await newPage(); await replay.goto(facts.activation_url, { waitUntil: 'load' });
    if (await replay.locator('form[action$="/account/activate"]').count()) {
      await submit(replay, '/account/activate', { password: memberPw, password_confirmation: memberPw });
    }
    must(!new URL(replay.url()).pathname.startsWith(p('/portal')) && /cannot be used|invalid|expired|already been used/i.test(await text(replay)), 'activation link worked twice');
    return `activated; portal shows ${facts.membership_number || 'membership number'}; admin 403; link refused on reuse`;
  });

  // ---- W4. Assessment of ₦50,000 -------------------------------------------
  await attempt('W4 Dues assessment ₦50,000 (draft, post, no double posting)', async () => {
    must(need('treasurer') && facts.membership_number, 'prerequisites missing');
    const t = officers.treasurer;
    await go(t, '/admin/finance/categories');
    await submit(t, '/admin/finance/categories', { code: `STG-${RUN}`, name: `Staging dues ${RUN} (synthetic)`, kind: 'dues' });
    await go(t, '/admin/finance/assessments/create');
    const catValue = await t.locator('select[name="fee_category_id"] option', { hasText: `STG-${RUN}` }).first().getAttribute('value')
      || await t.locator('select[name="fee_category_id"] option', { hasText: `Staging dues ${RUN}` }).first().getAttribute('value');
    await tick(t.locator('#mode-selected'));
    await tick(t.getByLabel(`Select Synthetic Acceptance Applicant ${RUN}`));
    await submit(t, '/admin/finance/assessments', { fee_category_id: catValue, title: `Staging assessment ${RUN}`, amount: '50,000', effective_on: daysAgo(20) });
    must((await text(t)).includes('₦50,000.00'), 'preview did not show ₦50,000.00: ' + (await errors(t)));
    const showPath = new URL(t.url()).pathname.replace(/^.*(\/admin\/finance\/assessments\/\d+)$/, '$1');
    await submit(t, showPath + '/post', { confirm: true });
    must(/posted/i.test(await text(t)), 'not posted: ' + (await errors(t)));
    must((await t.locator(`form[action$="${showPath}/post"]`).count()) === 0, 'post form still offered after posting');
    facts.assessment = showPath;
    return 'category created; assessment posted to 1 member; cannot be posted again';
  });

  // ---- W5. Payment ₦20,000: record, verify (other officer), allocate, reverse, re-allocate
  await attempt('W5 Manual ₦20,000 payment with separate verification, allocation and reversal', async () => {
    must(need('treasurer', 'payment_verifier') && facts.assessment, 'prerequisites missing');
    const t = officers.treasurer; const v = officers.payment_verifier;
    await go(t, '/admin/finance/payments/create');
    const memberValue = await t.locator('select[name="member_id"] option', { hasText: `Applicant ${RUN}` }).first().getAttribute('value');
    await submit(t, '/admin/finance/payments', { member_id: memberValue, amount: '20,000.00', method: 'bank_transfer', received_on: daysAgo(5), external_reference: `STG-TRF-${RUN}` });
    const payPath = new URL(t.url()).pathname.replace(/^.*(\/admin\/finance\/payments\/\d+)$/, '$1');
    must(/needs verification|pending/i.test(await text(t)), 'payment not pending');
    must((await t.locator(`form[action$="${payPath}/verify"]`).count()) === 0, 'recorder was offered verification');
    await go(v, payPath);
    await submit(v, payPath + '/verify', { note: 'Matched synthetic bank statement line', confirm: true });
    must(/verified/i.test(await text(v)), 'not verified: ' + (await errors(v)));
    await go(t, payPath);
    await t.locator('.alloc-input').first().fill('20000.01');
    await submit(t, payPath + '/allocate');
    must((await errors(t)).length > 0, 'over-allocation was not refused');
    await t.locator('.alloc-input').first().fill('20000');
    await submit(t, payPath + '/allocate');
    must(/Allocation recorded/i.test(await text(t)), 'allocation failed: ' + (await errors(t)));
    await go(v, payPath);
    const revForm = v.locator('form[action*="/admin/finance/allocations/"]').first();
    await v.locator('details:has(form[action*="/admin/finance/allocations/"]) > summary').first().click();
    await revForm.locator('[name="reason"]').fill('Synthetic correction: re-allocating');
    await Promise.all([v.waitForNavigation(), revForm.locator('button[type=submit]').click()]);
    must(/revers/i.test(await text(v)), 'allocation reversal not confirmed: ' + (await errors(v)));
    await go(t, payPath);
    await t.locator('.alloc-input').first().fill('20000');
    await submit(t, payPath + '/allocate');
    must(/Allocation recorded/i.test(await text(t)), 're-allocation failed: ' + (await errors(t)));
    facts.payment = payPath;
    return 'recorded pending; verified by a different officer; over-allocation refused; allocated, reversed and re-allocated';
  });

  // ---- W6. Statement and reports reconcile ------------------------------------
  await attempt('W6 Member statement and treasury reports reconcile (₦50,000 − ₦20,000 = ₦30,000)', async () => {
    must(member && need('treasurer'), 'prerequisites missing');
    await go(member, '/portal/finances/statement');
    const st = await text(member);
    must(st.includes('₦50,000.00') && st.includes('₦20,000.00') && st.includes('₦30,000.00'), 'member statement does not show 50,000 / 20,000 / 30,000');
    must((await member.locator('[data-print]').count()) === 1, 'no print button');
    await go(officers.treasurer, '/admin/finance/reports');
    const csvResp = await officers.treasurer.request.get(BASE + '/admin/finance/reports/balances.csv');
    const csv = await csvResp.text();
    const line = csv.split('\n').find((l) => l.includes(`Applicant ${RUN}`) || (facts.membership_number && l.includes(facts.membership_number)));
    must(line && line.includes('50000.00') && line.includes('20000.00') && line.includes('30000.00'), 'balances CSV line does not reconcile');
    return 'statement 50,000 / 20,000 / 30,000; print button present; balances CSV reconciles';
  });

  // ---- Integrity check ---------------------------------------------------------
  await attempt('Ledger integrity check from the alerts page', async () => {
    must(need('treasurer'), 'treasurer missing');
    await go(officers.treasurer, '/admin/alerts');
    await submit(officers.treasurer, '/admin/alerts/check');
    const t = await text(officers.treasurer);
    must(/passed/i.test(t) && !/open alert/i.test(t.split('Recent check runs')[0] || ''), 'check did not pass');
    return 'manual check run recorded as passed';
  });

  // ---- W7 (web part). Encrypted backup ------------------------------------------
  await attempt('W7 Encrypted backup created from the web (restore is a server-side step)', async () => {
    must(need('system_admin'), 'system admin missing');
    const a = officers.system_admin;
    await go(a, '/admin/backups');
    const enabled = await a.locator('form[action$="/admin/backups"] button[type=submit]').isEnabled();
    must(enabled, 'backup button disabled: BOBA_BACKUP_PUBLIC_KEY is not configured on staging');
    await submit(a, '/admin/backups');
    must(/succeeded|created/i.test(await text(a)), 'backup not reported as created: ' + (await errors(a)));
    return 'backup created and registered; run the isolated restore on the server (docs/36 §6)';
  });

  // ---- Content, events, enquiries ---------------------------------------------
  await attempt('Event draft → published and visible publicly', async () => {
    must(need('secretariat'), 'secretariat missing');
    const s = officers.secretariat;
    const when = new Date(today.getTime() + 30 * 86400000); const local = when.toISOString().slice(0, 10) + 'T18:00';
    await go(s, '/admin/events/create');
    await submit(s, '/admin/events', { title: `Synthetic staging event ${RUN}`, starts_at: local, audience: 'public' });
    const edit = new URL(s.url()).pathname.replace(/^.*(\/admin\/events\/\d+)$/, '$1');
    const pub = s.locator(`form[action$="${edit}/status"]:has(input[value="published"])`);
    await Promise.all([s.waitForNavigation(), pub.locator('button').click()]);
    const pa = await newPage(); await go(pa, '/events');
    must((await text(pa)).includes(`Synthetic staging event ${RUN}`), 'published event not on the public events page');
    return 'draft created, published, visible on /events';
  });
  await attempt('Contact enquiry reaches the secretariat queue', async () => {
    must(need('secretariat'), 'secretariat missing');
    const c = await newPage();
    await go(c, '/contact'); await c.waitForTimeout(4500);
    await submit(c, '/contact', { name: 'Synthetic Visitor', email: `visitor-${RUN.toLowerCase()}@${DOMAIN}`, topic: 'general', subject: `Staging enquiry ${RUN}`, message: 'Synthetic staging enquiry with ordinary words: select, update, <b>bold</b>.', consent: true });
    must(/reference|thank/i.test(await text(c)), 'no confirmation: ' + (await errors(c)));
    await go(officers.secretariat, '/admin/enquiries');
    must((await text(officers.secretariat)).includes(`Staging enquiry ${RUN}`), 'enquiry not in the queue');
    return 'submitted (incl. SQL/HTML-like words, ModSecurity check) and listed for the secretariat';
  });

  // ---- Responsive rendering --------------------------------------------------------
  await attempt('Responsive rendering 375 / 768 / 1440 px without horizontal overflow', async () => {
    const pages = [['anonymous', anon, ['/', '/membership', '/membership/apply', '/events', '/contact']]];
    if (member) pages.push(['member', member, ['/portal', '/portal/finances/statement']]);
    if (officers.treasurer) pages.push(['treasurer', officers.treasurer, ['/admin', '/admin/finance', '/admin/finance/reports']]);
    const bad = [];
    for (const [who, page, paths] of pages) for (const w of [375, 768, 1440]) {
      await page.setViewportSize({ width: w, height: 900 });
      for (const path of paths) {
        const st = await go(page, path);
        const [overflow, culprits] = await page.evaluate(() => {
          const W = document.documentElement.clientWidth;
          const exceeds = (e) => e && e.getBoundingClientRect && e.getBoundingClientRect().right > W + 1;
          const over = [...document.querySelectorAll('body *')].filter((e) => exceeds(e) && !exceeds(e.parentElement) && !e.closest('.sr-only'));
          return [document.documentElement.scrollWidth - W, over.slice(0, 3).map((e) => `${e.tagName.toLowerCase()}${e.className ? '.' + String(e.className).split(' ')[0] : ''} "${(e.innerText || '').slice(0, 30).replace(/\s+/g, ' ')}"`)];
        });
        if (st !== 200 || overflow > 1) bad.push(`${who} ${path} @${w}: HTTP ${st}, overflow ${overflow}px [${culprits.join(', ')}]`);
      }
    }
    must(bad.length === 0, bad.join('; '));
    return `${pages.reduce((n, x) => n + x[2].length, 0) * 3} page views clean`;
  });

  finish();

  function finish() {
    const failed = results.filter((r) => r.result !== 'pass').length;
    const summary = { executed_at: new Date().toISOString(), base: BASE, run: RUN, https: HTTPS, steps: results.length, failed, facts: { ...facts, activation_url: facts.activation_url ? '(redacted)' : undefined } };
    fs.writeFileSync(OUT, JSON.stringify({ summary, results }, null, 2));
    console.log(`\n${results.length - failed}/${results.length} steps passed — evidence written to ${OUT}`);
    Promise.all(contexts.map((c) => c.close())).then(() => browser.close()).then(() => process.exit(failed ? 1 : 0));
  }
})();
