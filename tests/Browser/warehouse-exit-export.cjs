const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE_PATH || 'playwright');

const root = path.resolve(__dirname, '../..');
const fixtures = path.join(root, 'storage/framework/testing/warehouse-export-browser');
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
            const context = await browser.newContext({ viewport, colorScheme: 'dark', acceptDownloads: true });
            const page = await context.newPage();
            const errors = [];
            let exports = 0;
            page.on('pageerror', error => errors.push(error.message));

            /** Le risposte provengono da richieste Laravel isolate: nessuna connessione raggiunge siti o database reali. */
            await context.route('**/*', async route => {
                try {
                    const request = route.request();
                    const url = new URL(request.url());
                    if (url.pathname === '/livewire/update' && request.method() === 'POST') {
                        const component = request.postDataJSON().components[0];
                        const method = component.calls[0]?.method;
                        let response;
                        if (!method && Object.keys(component.updates).some(key => key === 'selectedExitRowIds' || key.startsWith('selectedExitRowIds.'))) {
                            response = responses.selected;
                        } else if (method === 'openExportModal') {
                            response = responses.opened;
                        } else if (method === 'exportSelectedRows') {
                            let columns = [...JSON.parse(component.snapshot).data.selectedExportColumns[0]];
                            for (const [key, value] of Object.entries(component.updates)) {
                                if (key === 'selectedExportColumns') columns = value;
                                else if (key.startsWith('selectedExportColumns.')) {
                                    const index = Number(key.split('.')[1]);
                                    if (value === '__rm__') delete columns[index];
                                    else columns[index] = value;
                                }
                            }
                            columns = columns.filter(value => value !== undefined);
                            assert.ok(Array.isArray(columns), 'Le colonne devono essere inviate nella richiesta reale');
                            if (columns.length === 0) {
                                response = responses.invalid;
                            } else {
                                assert.deepEqual([...columns].sort(), ['color_note', 'customer', 'qty_in_phase']);
                                response = responses.download;
                                exports++;
                            }
                        } else {
                            throw new Error('Richiesta Livewire inattesa: ' + JSON.stringify({ method, updates: component.updates }));
                        }
                        return route.fulfill({ json: response });
                    }
                    if (/\/livewire\/livewire(?:\.min)?\.js$/.test(url.pathname)) {
                        return route.fulfill({ contentType: 'text/javascript', body: livewire });
                    }
                    if (url.hostname === 'warehouse-export.test' && url.pathname === '/') {
                        return route.fulfill({ contentType: 'text/html', body: html });
                    }
                    return route.abort();
                    } catch (error) {
                    errors.push(error.message);
                    await route.abort();
                }
            });

            await page.goto('http://warehouse-export.test/', { waitUntil: 'domcontentloaded' });
            await page.waitForFunction(() => window.Livewire && window.Livewire.all().length > 0);
            await page.evaluate(() => document.documentElement.classList.add('dark'));
            await page.locator('tbody input[type="checkbox"]').first().check();
            const exportButton = page.getByRole('button', { name: 'Esporta Excel', exact: true });
            await exportButton.click();
            const dialog = page.getByRole('dialog', { name: 'Esporta Excel', exact: true });
            await dialog.waitFor({ state: 'visible' });
            assert.equal(await dialog.getByRole('checkbox').count(), 10);
            assert.equal(await dialog.locator('input:checked').count(), 10);
            const bounds = await dialog.boundingBox();
            assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= viewport.width);
            assert.ok(bounds.y >= 0 && bounds.y + bounds.height <= viewport.height);
            assert.equal(await dialog.evaluate(element => element.contains(document.activeElement)), true);
            await page.keyboard.press('Escape');
            await dialog.waitFor({ state: 'hidden' });
            await exportButton.click();
            await dialog.waitFor({ state: 'visible' });
            await dialog.getByRole('button', { name: 'Annulla', exact: true }).click();
            await dialog.waitFor({ state: 'hidden' });
            await exportButton.click();
            await dialog.waitFor({ state: 'visible' });

            for (const checkbox of await dialog.getByRole('checkbox').all()) {
                await checkbox.uncheck();
            }
            await dialog.getByRole('button', { name: 'Scarica Excel', exact: true }).click();
            await dialog.getByRole('alert').filter({ hasText: 'Seleziona almeno una colonna' }).waitFor();
            assert.equal(exports, 0);
            for (const name of ['Cliente', 'Tessuto / Colore / Nota', 'Q.ty fase']) {
                await dialog.getByRole('checkbox', { name, exact: true }).check();
            }
            await page.screenshot({ path: path.join(fixtures, 'export-' + viewport.width + '.png') });
            const downloadPromise = page.waitForEvent('download');
            await dialog.getByRole('button', { name: 'Scarica Excel', exact: true }).click();
            const download = await downloadPromise;
            assert.equal(download.suggestedFilename(), responses.download.components[0].effects.download.name);
            const chunks = [];
            for await (const chunk of await download.createReadStream()) chunks.push(chunk);
            assert.deepEqual(Buffer.concat(chunks), Buffer.from(responses.download.components[0].effects.download.content, 'base64'));
            await dialog.waitFor({ state: 'hidden' });
            assert.equal(await page.locator('tbody input[type="checkbox"]').first().isChecked(), true);
            assert.deepEqual(errors, []);
            console.log(JSON.stringify({ viewport, columns: 10, validation: true, download: true, closeAndReopen: true, pageErrors: errors.length }));
            await context.close();
        }
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
