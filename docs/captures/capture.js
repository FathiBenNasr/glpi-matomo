// Regenerates the README screenshots against a GLPI bench, and doubles as a real-browser
// test: it fails if the loader does not start MTM on the login screen, if it runs for the
// administrator account or on the settings page (1.0.5 / 1.1.3), or if a non-admin page does
// not push the user identity to the data layer. Runs in the Puppeteer container; credentials
// come from the environment only: GLPI_URL, GLPI_USER, GLPI_PASS (administrator), OUT, and
// optionally TRACKED_USER, TRACKED_PASS (an account without administration rights).
const puppeteer = require('/app/scripts/node_modules/puppeteer');
const env = k => { const v = process.env[k]; if (!v) throw new Error('missing ' + k); return v; };
const out = n => `${env('OUT')}/${n}.png`;
const fail = m => { console.error('FAIL: ' + m); process.exitCode = 1; };
const dataLayer = p => p.evaluate(() => JSON.stringify(window._mtm || null));

(async () => {
  const b = await puppeteer.launch({executablePath: '/usr/bin/google-chrome',
    args: ['--no-sandbox', '--disable-gpu'], headless: 'new'});
  const p = await b.newPage();
  await p.setViewport({width: 1280, height: 860});
  // The container URL is fake on the bench: never let the page reach the Internet.
  await p.setRequestInterception(true);
  p.on('request', r => r.url().startsWith(env('GLPI_URL')) ? r.continue() : r.abort());
  const g = env('GLPI_URL');

  // ── Login screen: the container must start without any identity ──
  await p.goto(g + '/', {waitUntil: 'networkidle2'});
  const anon = await dataLayer(p);
  console.log('login screen _mtm =', anon);
  if (!anon || !anon.includes('mtm.Start')) fail('MTM not started on the login screen');
  if (anon && anon.includes('glpiUserId')) fail('identity leaked on an anonymous page');

  const login = async (user, pass) => {
    await p.goto(g + '/', {waitUntil: 'networkidle2'});
    await p.type('#login_name', user);
    await p.type('input[type=password]', pass);
    await Promise.all([p.waitForNavigation({waitUntil: 'networkidle2'}), p.click('button[type=submit]')]);
  };

  // ── Non-admin account (optional): identity pushed before the start event ──
  if (process.env.TRACKED_USER && process.env.TRACKED_PASS) {
    await login(process.env.TRACKED_USER, process.env.TRACKED_PASS);
    await p.goto(g + '/front/central.php', {waitUntil: 'networkidle2'});
    const auth = await dataLayer(p);
    console.log('non-admin _mtm =', auth);
    if (!auth || !/^\[\{"glpiUserId":"[^"]+"\},\{"mtm.startTime"/.test(auth)) fail('identity not pushed before mtm.Start');
    await p.deleteCookie(...(await p.cookies()));
  }

  // ── Administrator: the third-party container must not run at all ──
  await login(env('GLPI_USER'), env('GLPI_PASS'));
  await p.goto(g + '/front/central.php', {waitUntil: 'networkidle2'});
  const admin = await dataLayer(p);
  console.log('admin _mtm =', admin);
  if (admin !== 'null') fail('container loaded for an administrator session');

  // ── Settings page ──
  await p.goto(g + '/plugins/matomo/front/config.php', {waitUntil: 'networkidle2'});
  if ((await dataLayer(p)) !== 'null') fail('container loaded on the settings page');
  const form = await p.$('form[action$="/plugins/matomo/front/config.php"]');
  if (!form) { fail('settings form not found'); } else {
    await form.evaluate(f => f.scrollIntoView());
    await form.screenshot({path: out('01-settings')});
  }
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
