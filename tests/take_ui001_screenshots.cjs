'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('C:/Users/rodri/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');

const root = path.resolve(__dirname, '..');
const outDir = path.join(root, 'Docs', 'ui001-evidencias');
const brainDir = 'C:/Users/rodri/.gemini/antigravity/brain/2285d813-a91f-42d4-8568-71ec9774befb';

if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}

const targets = [
  // 1. Etapa A: Fim de Jogo (FIM DE JOGO badge, placar com escudos, competição, resumo XP, botão Avançar ->)
  { file: 'preview-ui001-etapa-a.html', name: 'ui001-01-etapa-a-fim-de-jogo-desktop-1200', vp: { width: 1200, height: 900 }, mobile: false },
  { file: 'preview-ui001-etapa-a.html', name: 'ui001-01-etapa-a-fim-de-jogo-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-ui001-etapa-a.html', name: 'ui001-01-etapa-a-fim-de-jogo-mobile-320', vp: { width: 320, height: 600 }, mobile: true },

  // 2. Etapa C: Decisão (após Avançar, decisões de fim de semana exibidas, fim de jogo oculto)
  { file: 'preview-ui001-etapa-c.html', name: 'ui001-02-etapa-c-decisao-desktop-1200', vp: { width: 1200, height: 900 }, mobile: false },
  { file: 'preview-ui001-etapa-c.html', name: 'ui001-02-etapa-c-decisao-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-ui001-etapa-c.html', name: 'ui001-02-etapa-c-decisao-mobile-320', vp: { width: 320, height: 600 }, mobile: true },

  // 3. Partida em andamento (competição no header e no scoreboard .match-time)
  { file: 'preview-ui001-partida.html', name: 'ui001-03-partida-competicao-desktop-1200', vp: { width: 1200, height: 900 }, mobile: false },
  { file: 'preview-ui001-partida.html', name: 'ui001-03-partida-competicao-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-ui001-partida.html', name: 'ui001-03-partida-competicao-mobile-320', vp: { width: 320, height: 600 }, mobile: true },
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
      await page.waitForTimeout(400);

      const outPath = path.join(outDir, t.name + '.png');
      const brainPath = path.join(brainDir, t.name + '.png');

      await page.screenshot({ path: outPath, fullPage: false });
      if (fs.existsSync(brainDir)) {
        fs.copyFileSync(outPath, brainPath);
      }

      console.log('Capturado: ' + t.name + ' (' + t.vp.width + 'x' + t.vp.height + ')');
      await context.close();
    }
  } finally {
    await browser.close();
  }
  console.log('Todas as capturas de UI-001 foram geradas e salvas com sucesso!');
})().catch(err => {
  console.error(err);
  process.exitCode = 1;
});
