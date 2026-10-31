const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');

const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/order-rejection-browser');
const responses = JSON.parse(fs.readFileSync(path.join(fixtures, 'responses.json'), 'utf8'));
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/build/manifest.json'), 'utf8'));
const css = fs.readFileSync(path.join(root, 'public/build', manifest['resources/css/app.css'].file), 'utf8');
const livewire = fs.readFileSync(path.join(root, 'vendor/livewire/livewire/dist/livewire.js'), 'utf8');
const html = fs.readFileSync(path.join(fixtures, 'page.html'), 'utf8')
    .replace('</head>', '<style>' + css + '</style></head>');

(async () => {
    const browser = await chromium.launch({
        headless: true,
        ...(process.env.TEST_CHROMIUM_PATH ? { executablePath: process.env.TEST_CHROMIUM_PATH } : {}),
    });
    try {
        for (const viewport of [{ width: 1440, height: 1000 }, { width: 390, height: 844 }]) {
            const context = await browser.newContext({ viewport, colorScheme: 'dark' });
            const page = await context.newPage();
            let dismissed = false;
            const polls = [];
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));

            /** Ogni richiesta è intercettata: il browser usa solo fixture e risorse locali, senza accedere a siti reali. */
            await context.route('**/*', async route => {
                const request = route.request();
                const url = new URL(request.url());
                if (url.pathname === '/livewire/update' && request.method() === 'POST') {
                    const payload = request.postDataJSON();
                    const method = payload.components[0].calls[0].method;
                    let response;
                    if (method === 'refreshAlerts') {
                        polls.push(Date.now());
                        response = dismissed ? responses.afterDismiss : responses.poll;
                    } else if (method === 'dismiss') {
                        dismissed = true;
                        response = responses.dismiss;
                    } else if (method === 'openAlert') {
                        response = responses.open;
                    } else {
                        throw new Error('Azione inattesa: ' + method);
                    }
                    return route.fulfill({ json: response });
                }
                if (url.pathname === '/livewire/livewire.js') {
                    return route.fulfill({ contentType: 'text/javascript', body: livewire });
                }
                if (/^\/alerts\/\d+$/.test(url.pathname)) {
                    return route.fulfill({ contentType: 'text/html', body: '<p>Collegamento ordine raggiunto</p>' });
                }
                if (url.hostname === 'rejection.test' && url.pathname === '/') {
                    return route.fulfill({ contentType: 'text/html', body: html });
                }
                return route.abort();
            });

            await page.goto('http://rejection.test/', { waitUntil: 'domcontentloaded' });
            await page.waitForFunction(() => window.Livewire && window.Livewire.all().length > 0);
            await page.evaluate(() => {
                document.documentElement.classList.add('dark');
                const input = document.createElement('input');
                input.id = 'unsaved-test-field';
                input.setAttribute('aria-label', 'Campo di prova');
                document.querySelector('main').append(input);
                document.querySelector('#main-content').scrollTop = 200;
            });
            await page.locator('#unsaved-test-field').fill('Testo non salvato');
            const initialUrl = page.url();
            const notice = page.locator('section[aria-label="Ordine rifiutato"]');
            const started = Date.now();
            assert.equal(await notice.count(), 0);
            await notice.waitFor({ state: 'visible', timeout: 16000 });
            assert.ok(polls[0] - started >= 8500, 'Il primo avviso deve attendere il controllo periodico');
            assert.equal(page.url(), initialUrl);
            assert.equal(await page.locator('#unsaved-test-field').inputValue(), 'Testo non salvato');
            assert.equal(await page.locator('#unsaved-test-field').evaluate(element => document.activeElement === element), true);
            const bounds = await notice.boundingBox();
            assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= viewport.width);
            assert.ok(bounds.y >= 0 && bounds.y + bounds.height <= viewport.height);
            await page.screenshot({ path: path.join(fixtures, 'notification-' + viewport.width + '.png') });

            if (viewport.width === 1440) {
                await notice.getByRole('button', { name: 'Chiudi questo avviso di ordine rifiutato' }).click();
                await notice.waitFor({ state: 'detached' });
                await page.waitForFunction(() => !document.querySelector('section[aria-label="Ordine rifiutato"]'));
                const nextPoll = polls.length;
                await page.waitForTimeout(10500);
                assert.ok(polls.length > nextPoll);
                assert.equal(await notice.count(), 0);
                assert.equal(await page.locator('#unsaved-test-field').inputValue(), 'Testo non salvato');
            } else {
                await notice.getByRole('link', { name: 'Apri ordine' }).click();
                await page.getByText('Collegamento ordine raggiunto').waitFor();
            }
            assert.deepEqual(errors, []);
            console.log(JSON.stringify({ viewport, automaticNotification: true, polls: polls.length, interaction: viewport.width === 1440 ? 'dismiss' : 'open-order', pageErrors: errors.length }));
            await context.close();
        }
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
