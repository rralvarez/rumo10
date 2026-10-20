'use strict';
const fs = require('fs');
const path = require('path');

/**
 * Resolução dinâmica e portável de dependência do Playwright.
 * Prioriza PLAYWRIGHT_PATH se fornecido pelo ambiente CI/container/host.
 * Em seguida tenta a resolução padrão do Node.js (node_modules local ou NODE_PATH).
 * Caso não encontre, encerra explicitamente com código de erro 1 sem mascarar falha.
 */
function getPlaywright() {
  const envPath = process.env.PLAYWRIGHT_PATH || process.env.PLAYWRIGHT_MODULE_PATH;
  if (envPath) {
    try {
      return require(envPath);
    } catch (e) {
      console.error(`Erro ao carregar Playwright de PLAYWRIGHT_PATH ("${envPath}"): ${e.message}`);
      process.exit(1);
    }
  }
  try {
    return require('playwright');
  } catch (e) {
    console.error('Dependência "playwright" não encontrada no ambiente Node.js.');
    console.error('Instruções de execução e reprodutibilidade:');
    console.error('  1. Para instalar localmente: npm install --save-dev playwright');
    console.error('  2. Ou forneça a variável de ambiente: PLAYWRIGHT_PATH=/caminho/para/node_modules/playwright');
    console.error('  3. Assegure-se de que os snapshots HTML foram gerados previamente por:');
    console.error('     php tests/state-program-http-v07.php --local-fixtures');
    process.exit(1);
  }
}

const { chromium } = getPlaywright();

(async () => {
  const httpDir = path.join('storage', 'qa', 'states27', 'http');
  if (!fs.existsSync(httpDir) || !fs.existsSync(path.join(httpDir, 'carreira.html'))) {
    console.error(`Fixtures HTML necessárias não encontradas em "${httpDir}".`);
    console.error('Gere os arquivos executando previamente: php tests/state-program-http-v07.php --local-fixtures');
    process.exit(1);
  }

  const out = process.env.V07_MOBILE_EVIDENCE_DIR || 'Docs/mundializacao-v07/conclusao-27/mobile';
  fs.mkdirSync(out, { recursive: true });

  const launchOpts = { headless: true };
  const chromePath = process.env.CHROME_BIN || process.env.CHROME_PATH || (process.platform === 'win32' && fs.existsSync('C:/Program Files/Google/Chrome/Application/chrome.exe') ? 'C:/Program Files/Google/Chrome/Application/chrome.exe' : undefined);
  if (chromePath) {
    launchOpts.executablePath = chromePath;
  }

  const b = await chromium.launch(launchOpts);
  let checks = 0;
  const errors = [];
  const ok = (v, m) => {
    checks++;
    if (!v) throw Error(m);
  };

  try {
    const p = await b.newPage({ reducedMotion: 'reduce' });
    p.on('pageerror', e => errors.push(e.message));
    await p.route('http://world.test/**', r => {
      const u = new URL(r.request().url());
      if (u.pathname.startsWith('/assets/') || u.pathname.startsWith('/favicon')) {
        const file = path.join('public', u.pathname);
        return r.fulfill({
          body: fs.readFileSync(file),
          contentType: u.pathname.endsWith('.css') ? 'text/css' : u.pathname.endsWith('.js') ? 'application/javascript' : u.pathname.endsWith('.svg') ? 'image/svg+xml' : 'image/png'
        });
      }
      return r.fulfill({
        body: fs.readFileSync(path.join('storage', 'qa', 'states27', 'http', u.pathname.slice(1).replaceAll('/', '-') + '.html')),
        contentType: 'text/html; charset=utf-8'
      });
    });

    for (const width of [320, 390, 1280]) {
      for (const route of ['carreira', 'jogador', 'classificacao', 'classificacao-ce-ramos', 'carreira/trofeus', 'carreira-rj-pendente', 'carreira-am-recinto']) {
        await p.setViewportSize({ width, height: 900 });
        await p.goto('http://world.test/' + route);
        await p.waitForLoadState('networkidle');
        ok(await p.locator('.world-calendar-notice').isVisible(), 'adaptação visível');
        if (route.startsWith('classificacao-estadual-')) {
          ok(await p.getByRole('heading', { name: /Classificação ·/ }).isVisible(), 'classificação estadual realmente renderizada');
        }
        ok(!await p.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), 'sem overflow ' + width + route);
        ok(await p.getByRole('navigation', { name: 'Menu principal' }).count() === 1, 'navegação íntegra');
        await p.screenshot({ path: path.join(out, route.replaceAll('/', '-') + '-' + width + '.png'), fullPage: true });
      }
    }

    await p.setViewportSize({ width: 320, height: 900 });
    await p.goto('http://world.test/carreira');
    await p.locator('.menu-more summary').focus();
    await p.keyboard.press('Enter');
    ok(await p.locator('.menu-more').getAttribute('open') !== null, 'teclado abre menu');
    await p.keyboard.press('Escape');
    ok(await p.locator('.menu-more').getAttribute('open') === null, 'Escape fecha menu');
    ok(errors.length === 0, 'sem erros JS');

    fs.writeFileSync(path.join(out, 'results.json'), JSON.stringify({ checks, errors, transport: 'Chrome against captured authenticated loopback fixture HTML; not real device/IIS/Apache' }, null, 2));
    console.log('OK: ' + checks + ' verificações visuais do piloto.');
  } finally {
    await b.close();
  }
})();
