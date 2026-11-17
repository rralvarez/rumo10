'use strict';

/**
 * R10-AUD-001 | Teste End-to-End no Navegador Real (Google Chrome) contra IIS Local
 * Executa áudio real no IIS (http://rumo10.local), avalia decodificação Web Audio real,
 * requisições de rede, reprodução de buffers OGG físicos e deduplicação em sessionStorage.
 */

const fs = require('fs');
const path = require('path');

let passCount = 0;
let failCount = 0;

function assert(cond, msg) {
  if (cond) {
    console.log(`  [PASS] ${msg}`);
    passCount++;
  } else {
    console.error(`  [FAIL] ${msg}`);
    failCount++;
    throw new Error(`Assertion failed: ${msg}`);
  }
}

// 1. Resolução dinâmica e portável do Playwright
function resolvePlaywright() {
  if (process.env.PLAYWRIGHT_PATH) {
    try { return require(process.env.PLAYWRIGHT_PATH); } catch (_) {}
  }
  try { return require('playwright'); } catch (_) {}
  try { return require('playwright-core'); } catch (_) {}

  const candidates = [
    path.join(process.env.USERPROFILE || '', '.cache', 'codex-runtimes', 'codex-primary-runtime', 'dependencies', 'node', 'node_modules', 'playwright'),
    path.join(process.env.HOME || '', '.cache', 'node_modules', 'playwright')
  ];
  for (const cand of candidates) {
    if (cand && fs.existsSync(cand)) {
      try { return require(cand); } catch (_) {}
    }
  }
  return null;
}

// 2. Resolução dinâmica e portável do executável Chrome / Chromium
function resolveChromePath() {
  if (process.env.CHROME_BIN && fs.existsSync(process.env.CHROME_BIN)) {
    return process.env.CHROME_BIN;
  }
  if (process.env.PUPPETEER_EXECUTABLE_PATH && fs.existsSync(process.env.PUPPETEER_EXECUTABLE_PATH)) {
    return process.env.PUPPETEER_EXECUTABLE_PATH;
  }

  const isWin = process.platform === 'win32';
  const isMac = process.platform === 'darwin';

  if (isWin) {
    const winPaths = [
      'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
      path.join(process.env.LOCALAPPDATA || '', 'Google', 'Chrome', 'Application', 'chrome.exe'),
      'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'
    ];
    for (const p of winPaths) {
      if (fs.existsSync(p)) return p;
    }
  } else if (isMac) {
    const macPaths = [
      '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
      '/Applications/Chromium.app/Contents/MacOS/Chromium'
    ];
    for (const p of macPaths) {
      if (fs.existsSync(p)) return p;
    }
  } else {
    const linuxPaths = [
      '/usr/bin/google-chrome',
      '/usr/bin/chromium',
      '/usr/bin/chromium-browser'
    ];
    for (const p of linuxPaths) {
      if (fs.existsSync(p)) return p;
    }
  }
  return undefined;
}

(async () => {
  console.log('=== R10-AUD-001: TESTE E2E NO CHROME COM ÁUDIO REAL NO IIS ===\n');

  const pw = resolvePlaywright();
  if (!pw || !pw.chromium) {
    console.log('  [SKIP] Playwright não disponível no ambiente de execução.');
    console.log('  Para executar o teste E2E do navegador: npm install -D playwright (ou configure PLAYWRIGHT_PATH).');
    process.exit(0);
  }
  const { chromium } = pw;
  const chromePath = resolveChromePath();

  const launchOptions = {
    headless: true,
    args: [
      '--autoplay-policy=no-user-gesture-required',
      '--disable-features=PreloadMediaEngagementData,AutoplayIgnoreWebAudio',
      '--no-sandbox'
    ]
  };
  if (chromePath) {
    launchOptions.executablePath = chromePath;
  }

  const browser = await chromium.launch(launchOptions);

  const context = await browser.newContext();
  const page = await context.newPage();

  const networkResponses = new Map();
  page.on('response', response => {
    const url = response.url();
    if (url.includes('/assets/audio/')) {
      networkResponses.set(url.split('/').pop(), response.status());
    }
  });

  // 1. Acesso ao IIS local
  console.log('1. Conectando ao IIS local em http://rumo10.local...');
  await page.goto('http://rumo10.local/', { waitUntil: 'domcontentloaded' });
  assert(page.url().includes('rumo10.local'), 'Página carregada com sucesso a partir do IIS local');

  // 2. Verificação de integridade do AudioEngine injetado
  console.log('\n2. Verificando inicialização do AudioEngine no Chrome...');
  const engineReady = await page.evaluate(() => {
    return !!window.__rumo10Audio && !!window.__rumo10AudioCtx;
  });
  assert(engineReady, 'AudioEngine e AudioContext nativos ativos no navegador');

  // 3. Teste de decodificação e playback real de match_whistle.ogg
  console.log('\n3. Testando decodificação Web Audio real de match_whistle.ogg no IIS...');
  const whistleResult = await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    try {
      const buffer = await audio.bufferFor('match_whistle.ogg');
      return {
        success: true,
        duration: buffer.duration,
        channels: buffer.numberOfChannels,
        sampleRate: buffer.sampleRate
      };
    } catch (err) {
      return { success: false, error: err.message };
    }
  });

  assert(whistleResult.success, 'match_whistle.ogg decodificado com sucesso pela Web Audio API');
  assert(whistleResult.duration > 0.4 && whistleResult.duration < 0.6, `Duração aferida na decodificação real: ${whistleResult.duration.toFixed(3)}s`);
  assert(whistleResult.channels === 1 || whistleResult.channels === 2, `Canais de áudio válidos: ${whistleResult.channels}`);
  assert(networkResponses.get('match_whistle.ogg') === 200, 'IIS respondeu HTTP 200 para match_whistle.ogg');

  // 4. Teste de decodificação real de todos os arquivos OGG disponíveis
  console.log('\n4. Testando decodificação real de todas as amostras OGG via HTTP local...');
  const filesToTest = ['goal.ogg', 'assist.ogg', 'yellow_card.ogg', 'red_card.ogg', 'substitution_in.ogg', 'substitution_out.ogg'];
  for (const f of filesToTest) {
    const res = await page.evaluate(async (file) => {
      const audio = window.__rumo10Audio;
      try {
        const buffer = await audio.bufferFor(file);
        return { success: true, duration: buffer.duration };
      } catch (e) {
        return { success: false, error: e.message };
      }
    }, f);
    assert(res.success, `Amostra ${f} decodificada no Chrome com duração ${res.duration.toFixed(2)}s`);
    assert(networkResponses.get(f) === 200, `IIS entregou HTTP 200 para /assets/audio/${f}`);
  }

  // 5. Teste de reprodução direta de apito de intervalo (2x sobreposto)
  console.log('\n5. Executando playback real de apito com sobreposição (MATCH_HALFTIME)...');
  const halftimePlayback = await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    const t0 = audio.ctx.currentTime;
    const ok = await audio.playDirect('MATCH_HALFTIME');
    return { ok, elapsed: audio.ctx.currentTime - t0 };
  });
  assert(halftimePlayback.ok, 'MATCH_HALFTIME executado com sobreposição sem exceção');

  // 6. Teste de Fila Concorrente (Gol 90' seguido de Apito Final)
  console.log('\n6. Testando Fila Real no DOM (Gol + Apito Final na mesma página)...');
  const queueResult = await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    sessionStorage.clear();

    // Injeta elementos no DOM
    const divGoal = document.createElement('div');
    divGoal.dataset.soundEvent = 'GOAL_SCORED';
    divGoal.dataset.soundEventId = 'e2e:goal:90';
    document.body.appendChild(divGoal);

    const divWhistle = document.createElement('div');
    divWhistle.dataset.soundEvent = 'MATCH_WHISTLE_END';
    divWhistle.dataset.soundEventId = 'e2e:whistle:90';
    document.body.appendChild(divWhistle);

    // Enfileira manualmente no motor para auditar a ordem de prioridade
    audio.enqueue('e2e:goal:90', 'GOAL_SCORED');
    audio.enqueue('e2e:whistle:90', 'MATCH_WHISTLE_END');

    const hasGoalInQueue = audio.playing || audio.wasHandled('e2e:goal:90');
    const whistleInPending = audio.pendingQueue.has('e2e:whistle:90');

    // Aguardar conclusão da reprodução do gol (duração 2.8s)
    await new Promise(r => setTimeout(r, 3200));

    return {
      hasGoalInQueue,
      whistleInPending,
      goalHandled: audio.wasHandled('e2e:goal:90'),
      whistleStarted: audio.playing || audio.wasHandled('e2e:whistle:90')
    };
  });

  assert(queueResult.hasGoalInQueue, 'GOAL_SCORED assumiu playback prioritário');
  assert(queueResult.whistleInPending, 'MATCH_WHISTLE_END permaneceu retido na pendingQueue sem descarte');
  assert(queueResult.goalHandled, 'GOAL_SCORED registrado como concluído no sessionStorage');

  // 7. Teste de Mute / Unmute no DOM
  console.log('\n7. Testando alternância de mudo (Mute UI)...');
  const muteResult = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    audio.setMuted(true);
    const wasMuted = audio.prefs.muted;
    audio.setMuted(false);
    const wasUnmuted = !audio.prefs.muted;
    return { wasMuted, wasUnmuted };
  });
  assert(muteResult.wasMuted && muteResult.wasUnmuted, 'Controles de mudo/desmudo operacionais');

  await browser.close();

  console.log('\n=======================================================');
  console.log(`TOTAL E2E NAVEGADOR REAL: ${passCount} verificações PASSOU, ${failCount} FALHOU`);
  console.log('=======================================================');

  if (failCount > 0) process.exit(1);
})().catch(err => {
  console.error('Fatal error in Chrome E2E test:', err);
  process.exit(1);
});
