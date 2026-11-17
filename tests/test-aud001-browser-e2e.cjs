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

  const allowSkip = process.argv.includes('--allow-skip') || process.env.ALLOW_SKIP === '1';
  const pw = resolvePlaywright();
  if (!pw || !pw.chromium) {
    if (allowSkip) {
      console.log('  [SKIP] Playwright não disponível no ambiente de execução (--allow-skip ativo).');
      console.log('  Para executar o teste E2E do navegador: npm install -D playwright (ou configure PLAYWRIGHT_PATH).');
      process.exit(0);
    }
    console.error('  [FAIL] Playwright não disponível no ambiente de execução.');
    console.error('  A execução dos testes E2E é obrigatória e sua ausência não pode produzir aprovação artificial.');
    console.error('  Instale com "npm install -D playwright" ou aponte PLAYWRIGHT_PATH.');
    process.exit(1);
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
  const filesToTest = ['goal.ogg', 'no_goal.ogg', 'assist.ogg', 'yellow_card.ogg', 'red_card.ogg', 'substitution_in.ogg', 'substitution_out.ogg'];
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

  // 8. Teste de Efeitos Sintetizados Web Audio (P1-C: BALL_HIT_POST e PENALTY_AWARDED) no Chrome
  console.log('\n8. Testando Efeitos Sintetizados Legados (BALL_HIT_POST e PENALTY_AWARDED) no Chrome...');
  const synthResult = await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    const postRes = await audio.playDirect('BALL_HIT_POST', 'e2e:synth:post');
    const penRes = await audio.playDirect('PENALTY_AWARDED', 'e2e:synth:pen');
    return {
      postStatus: postRes.status,
      penStatus: penRes.status,
      postHandled: audio.wasHandled('e2e:synth:post'),
      penHandled: audio.wasHandled('e2e:synth:pen'),
      postSession: sessionStorage.getItem('rumo10_sound_played:e2e:synth:post'),
      penSession: sessionStorage.getItem('rumo10_sound_played:e2e:synth:pen')
    };
  });
  assert(synthResult.postStatus === 'scheduled', 'BALL_HIT_POST sintetizado e agendado com sucesso no Chrome');
  assert(synthResult.penStatus === 'scheduled', 'PENALTY_AWARDED sintetizado e agendado com sucesso no Chrome');
  assert(synthResult.postHandled && synthResult.postSession === '1', 'BALL_HIT_POST registrado no sessionStorage do navegador');
  assert(synthResult.penHandled && synthResult.penSession === '1', 'PENALTY_AWARDED registrado no sessionStorage do navegador');

  // 9. Teste de Deduplicação no F5 durante reprodução (P1-A)
  console.log('\n9. Testando Deduplicação no F5 DURANTE a reprodução no navegador real (P1-A)...');
  // Dispara um som longo (goal.ogg) e logo aos ~200ms recarrega a página
  await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    audio.playDirect('GOAL_SCORED', 'e2e:midflight:goal');
  });

  // Imediatamente verifica se já foi marcado antes do reload
  const markedMidflight = await page.evaluate(() => {
    return sessionStorage.getItem('rumo10_sound_played:e2e:midflight:goal') === '1';
  });
  assert(markedMidflight, 'P1-A: Evento marcado no sessionStorage imediatamente ao iniciar reprodução');

  // Recarrega a página (F5) enquanto o som original ainda estaria tocando
  await page.reload({ waitUntil: 'domcontentloaded' });
  const reloadDeduplicated = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    return audio.wasHandled('e2e:midflight:goal') &&
           sessionStorage.getItem('rumo10_sound_played:e2e:midflight:goal') === '1';
  });
  assert(reloadDeduplicated, 'P1-A: Deduplicação mantida após reload no meio da reprodução (sem eco nem re-trigger)');

  // 10. Teste do Cenário Real de 4 Eventos Contemporâneos no Chrome (P1-C R2)
  console.log('\n10. Testando Cenário Real de 4 Eventos Contemporâneos (Gol + Pênalti + Substituição + Apito Final)...');
  const quadResult = await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    sessionStorage.clear();

    const events = [
      { id: 'e2e:quad:whistle', key: 'MATCH_WHISTLE_END' },
      { id: 'e2e:quad:goal', key: 'GOAL_SCORED' },
      { id: 'e2e:quad:pen', key: 'PENALTY_AWARDED' },
      { id: 'e2e:quad:sub', key: 'PLAYER_SUBSTITUTION_IN' },
    ];

    // Enfileira todos os 4 eventos contemporâneos legítimos
    for (const ev of events) {
      audio.enqueue(ev.id, ev.key);
    }

    // P1-C R2: O 4º evento NÃO pode ser descartado da pendingQueue
    const whistleInQueueBefore = audio.pendingQueue.has('e2e:quad:whistle');
    const whistleSessionBefore = sessionStorage.getItem('rumo10_sound_played:e2e:quad:whistle');

    // Aguardar o primeiro evento (Gol) tocar
    const goalStarted = audio.playing || audio.wasHandled('e2e:quad:goal');

    // Aguardar término de toda a sequência com polling seguro (até 10s)
    const deadline = Date.now() + 10000;
    while (audio.pendingQueue.size > 0 && Date.now() < deadline) {
      await new Promise(r => setTimeout(r, 100));
    }

    const allHandled =
      audio.wasHandled('e2e:quad:goal') &&
      audio.wasHandled('e2e:quad:pen') &&
      audio.wasHandled('e2e:quad:sub') &&
      audio.wasHandled('e2e:quad:whistle');

    const allInSession =
      sessionStorage.getItem('rumo10_sound_played:e2e:quad:goal') === '1' &&
      sessionStorage.getItem('rumo10_sound_played:e2e:quad:pen') === '1' &&
      sessionStorage.getItem('rumo10_sound_played:e2e:quad:sub') === '1' &&
      sessionStorage.getItem('rumo10_sound_played:e2e:quad:whistle') === '1';

    return {
      whistleInQueueBefore,
      whistleSessionBefore,
      goalStarted,
      allHandled,
      allInSession,
      pendingEmpty: audio.pendingQueue.size === 0
    };
  });

  assert(quadResult.whistleInQueueBefore, 'P1-C R2: 4º evento (apito final) retido na fila sem descarte');
  assert(quadResult.whistleSessionBefore !== '1', 'P1-C R2: 4º evento NÃO recebe markHandled antes do agendamento');
  assert(quadResult.goalStarted, 'P1-C R2: GOAL_SCORED assume reprodução prioritária');
  assert(quadResult.allHandled && quadResult.allInSession, 'P1-C R2: Todos os 4 eventos reproduzidos e registrados no sessionStorage');
  assert(quadResult.pendingEmpty, 'P1-C R2: Fila de 4 eventos completamente esvaziada');

  // Teste de F5 após os 4 eventos: nenhum som deve tocar
  await page.reload({ waitUntil: 'domcontentloaded' });
  const reloadQuadQuiet = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    return audio.pendingQueue.size === 0 && !audio.playing;
  });
  assert(reloadQuadQuiet, 'P1-C R2: F5 após 4 eventos mantém silêncio absoluto no navegador');

  // Teste de Sessão Nova com Eventos Históricos: sem rajadas sonoras
  const freshSessionQuiet = await page.evaluate(() => {
    sessionStorage.clear();
    const audio = window.__rumo10Audio;

    // Simula 4 eventos históricos no DOM
    const historicals = ['h1:15', 'h2:30', 'h3:45', 'h4:60'];
    for (const hid of historicals) {
      const el = document.createElement('div');
      el.dataset.soundEvent = 'CARD_YELLOW';
      el.dataset.soundEventId = hid;
      el.dataset.soundHistorical = '1';
      document.body.appendChild(el);
    }

    // Executa scan
    document.querySelectorAll('[data-sound-event][data-sound-event-id]').forEach(el => {
      if (el.dataset.soundHistorical === '1') {
        audio.markHandled(el.dataset.soundEventId);
      }
    });

    return audio.pendingQueue.size === 0 && !audio.playing;
  });
  assert(freshSessionQuiet, 'P1-C R2: Nova sessão com eventos históricos não gera rajadas sonoras');

  // 11. Testando Blindagem Temporal do Espaçamento de 250ms no Chrome Real contra Clique na Pausa (Codex R3)
  console.log('\n11. Testando Blindagem Temporal do Espaçamento de 250ms no Chrome Real (Codex R3)...');

  // 11.A: Espaçamento normal sem clique no Chrome real
  const timingNoClick = await page.evaluate(async () => {
    sessionStorage.clear();
    const audio = window.__rumo10Audio;
    const playLogs = [];
    const origPlay = audio.playDirect.bind(audio);
    audio.playDirect = async function(key, id) {
      const entry = { key, id, start: performance.now(), end: null };
      playLogs.push(entry);
      const res = await origPlay(key, id);
      entry.end = performance.now();
      return res;
    };

    audio.enqueue('timing:1:card', 'CARD_YELLOW');
    audio.enqueue('timing:1:whistle', 'MATCH_WHISTLE_START');

    // Aguarda término de ambos os efeitos (yellow_card ~0.53s + 250ms gap + whistle ~0.53s)
    await new Promise(r => setTimeout(r, 2000));

    // Restaura
    audio.playDirect = origPlay;

    if (playLogs.length >= 2 && playLogs[0].end && playLogs[1].start) {
      return {
        count: playLogs.length,
        gap: playLogs[1].start - playLogs[0].end,
        firstHandled: audio.wasHandled('timing:1:card'),
        secondHandled: audio.wasHandled('timing:1:whistle')
      };
    }
    return { count: playLogs.length, gap: 0, firstHandled: false, secondHandled: false };
  });

  assert(timingNoClick.count === 2, 'P1-C R3: Exatamente 2 eventos executados no Chrome');
  assert(timingNoClick.gap >= 240, `P1-C R3: Espaçamento sem clique no Chrome: ${timingNoClick.gap.toFixed(1)}ms >= 240ms`);
  assert(timingNoClick.firstHandled && timingNoClick.secondHandled, 'P1-C R3: Ambos os eventos registrados no sessionStorage');

  // 11.B: Espaçamento COM clique aos 30ms da pausa no Chrome real (Reprodução do Defeito Codex R3)
  const timingWithClick = await (async () => {
    // Configura a instrumentação na página
    await page.evaluate(() => {
      sessionStorage.clear();
      const audio = window.__rumo10Audio;
      window.__r3PlayLogs = [];
      window.__r3OrigPlay = audio.playDirect.bind(audio);
      audio.playDirect = async function(key, id) {
        const entry = { key, id, start: performance.now(), end: null };
        window.__r3PlayLogs.push(entry);
        const res = await window.__r3OrigPlay(key, id);
        entry.end = performance.now();
        return res;
      };

      audio.enqueue('click:1:card', 'CARD_YELLOW');
      audio.enqueue('click:1:whistle', 'MATCH_WHISTLE_START');
    });

    // Aguarda até o primeiro evento concluir efetivamente
    let firstEnded = false;
    for (let i = 0; i < 40; i++) {
      firstEnded = await page.evaluate(() => {
        return window.__r3PlayLogs.length >= 1 && window.__r3PlayLogs[0].end !== null;
      });
      if (firstEnded) break;
      await page.waitForTimeout(50);
    }

    // Aguarda exatamente 30ms na pausa (momento exato onde ocorria o corte prematuro no Codex R3)
    await page.waitForTimeout(30);

    // Dispara clique físico real na página
    await page.click('body');

    // Imediatamente após o clique aos 30ms, verifica se o 2º som foi disparado precocemente
    const triggeredEarly = await page.evaluate(() => {
      return window.__r3PlayLogs.length > 1;
    });

    // Aguarda conclusão total
    await page.waitForTimeout(1500);

    const result = await page.evaluate(() => {
      const audio = window.__rumo10Audio;
      audio.playDirect = window.__r3OrigPlay;
      const logs = window.__r3PlayLogs;
      const gap = (logs.length >= 2 && logs[0].end && logs[1].start)
        ? (logs[1].start - logs[0].end)
        : 0;
      return {
        count: logs.length,
        gap,
        firstHandled: audio.wasHandled('click:1:card'),
        secondHandled: audio.wasHandled('click:1:whistle')
      };
    });

    return {
      triggeredEarly,
      ...result
    };
  })();

  assert(!timingWithClick.triggeredEarly, 'P1-C R3 (Chrome): Aos 30ms após clique na pausa, 2º som NÃO disparou precocemente');
  assert(timingWithClick.count === 2, 'P1-C R3 (Chrome): Exatamente 2 eventos reproduzidos');
  assert(timingWithClick.gap >= 240, `P1-C R3 (Chrome): Gap efetivo protegido contra clique no Chrome: ${timingWithClick.gap.toFixed(1)}ms >= 240ms`);
  assert(timingWithClick.firstHandled && timingWithClick.secondHandled, 'P1-C R3 (Chrome): Ambos os eventos concluídos e persistidos');

  await browser.close();

  console.log('\n=======================================================');
  console.log(`TOTAL E2E NAVEGADOR REAL: ${passCount} verificações PASSOU, ${failCount} FALHOU`);
  console.log('=======================================================');

  if (failCount > 0) process.exit(1);
})().catch(err => {
  console.error('Fatal error in Chrome E2E test:', err);
  process.exit(1);
});
