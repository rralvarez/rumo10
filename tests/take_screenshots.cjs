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
  // 1200px Desktop
  { file: 'preview-30m.html', name: '01-progresso-30m-desktop-1200', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'preview-45m.html', name: '02-progresso-45m-desktop-1200', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'preview-60m.html', name: '03-progresso-60m-desktop-1200', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'preview-90m.html', name: '04-progresso-90m-desktop-1200', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'preview-expulso.html', name: '05-jogador-expulso-desktop-1200', vp: { width: 1200, height: 820 }, mobile: false },

  // 390px Mobile (iPhone padrão)
  { file: 'preview-30m.html', name: '01-progresso-30m-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-45m.html', name: '02-progresso-45m-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-60m.html', name: '03-progresso-60m-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-90m.html', name: '04-progresso-90m-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-expulso.html', name: '05-jogador-expulso-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-substituido.html', name: '06-jogador-substituido-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-suspenso.html', name: '07-jogador-suspenso-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'preview-legado-banco.html', name: '08-legado-banco-mobile-390', vp: { width: 390, height: 844 }, mobile: true },

  // 320px Mobile Pequeno (compacto)
  { file: 'preview-30m.html', name: '01-progresso-30m-mobile-320', vp: { width: 320, height: 600 }, mobile: true },
  { file: 'preview-45m.html', name: '02-progresso-45m-mobile-320', vp: { width: 320, height: 600 }, mobile: true },
  { file: 'preview-60m.html', name: '03-progresso-60m-mobile-320', vp: { width: 320, height: 600 }, mobile: true },
  { file: 'preview-90m.html', name: '04-progresso-90m-mobile-320', vp: { width: 320, height: 600 }, mobile: true },
  { file: 'preview-expulso.html', name: '05-jogador-expulso-mobile-320', vp: { width: 320, height: 600 }, mobile: true },

  // OS-COR-F03-R6: Validações Visuais Específicas
  // 1. EC São Bernardo-SP x Marília-SP no intervalo (nome longo sem corte na borda)
  { file: 'test-r6-halftime.html', name: 'r6-01-bernardo-marilia-intervalo-desktop-1200', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'test-r6-halftime.html', name: 'r6-01-bernardo-marilia-intervalo-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'test-r6-halftime.html', name: 'r6-01-bernardo-marilia-intervalo-mobile-320', vp: { width: 320, height: 600 }, mobile: true },

  // 2. Tela de encerramento (acontecimentos e resultado final perfeitamente alinhados no topo)
  { file: 'test-r6-fim.html', name: 'r6-02-encerramento-alinhado-desktop-1200', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'test-r6-fim.html', name: 'r6-02-encerramento-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'test-r6-fim.html', name: 'r6-02-encerramento-mobile-320', vp: { width: 320, height: 600 }, mobile: true },

  // 3. Casos extremos: dois dígitos e nomes longos (12 x 10)
  { file: 'extreme-andamento.html', name: 'r6-03-dois-digitos-nomes-longos-desktop-1200', vp: { width: 1200, height: 820 }, mobile: false },
  { file: 'extreme-andamento.html', name: 'r6-03-dois-digitos-nomes-longos-mobile-390', vp: { width: 390, height: 844 }, mobile: true },
  { file: 'extreme-andamento.html', name: 'r6-03-dois-digitos-nomes-longos-mobile-320', vp: { width: 320, height: 600 }, mobile: true }
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
  console.log('Todas as capturas de correção F03 foram salvas com sucesso!');
})().catch(err => {
  console.error(err);
  process.exitCode = 1;
});
