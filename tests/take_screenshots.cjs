'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('C:/Users/rodri/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');

const root = path.resolve(__dirname, '..');
const outDir = path.join(root, 'Docs', 'f03-evidencias');
const brainDir = 'C:/Users/rodri/.gemini/antigravity/brain/2285d813-a91f-42d4-8568-71ec9774befb';

if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}

const targets = [
  { file: 'preview-1t.html', name: '01-partida-1tempo-desktop', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'preview-1t.html', name: '01-partida-1tempo-mobile', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-intervalo.html', name: '02-partida-intervalo-desktop', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'preview-intervalo.html', name: '02-partida-intervalo-mobile', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-2t.html', name: '03-partida-2tempo-desktop', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'preview-2t.html', name: '03-partida-2tempo-mobile', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-banco.html', name: '04-partida-banco-mobile', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-substituido.html', name: '05-partida-substituido-mobile', vp: { width: 390, height: 844 }, mobile: true }
];

(async () => {
  const browser = await chromium.launch({
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    headless: true
  });

  try {
    for (const t of targets) {
      const context = await browser.newContext({
        viewport: t.vp,
        isMobile: t.mobile,
        deviceScaleFactor: 2
      });
      const page = await context.newPage();
      const url = 'http://rumo10.local/' + t.file;
      await page.goto(url, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(500);
      
      const outPath = path.join(outDir, t.name + '.png');
      const brainPath = path.join(brainDir, t.name + '.png');
      
      await page.screenshot({ path: outPath, fullPage: false });
      fs.copyFileSync(outPath, brainPath);
      
      console.log('Capturado: ' + t.name + ' (' + t.vp.width + 'x' + t.vp.height + ')');
      await context.close();
    }
  } finally {
    await browser.close();
  }
  console.log('Todas as capturas foram salvas com sucesso!');
})().catch(err => {
  console.error(err);
  process.exitCode = 1;
});
