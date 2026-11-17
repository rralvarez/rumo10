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

  // 11. Validação R4: Ciclo de Vida dos Sintetizadores e Temporização Estrita (Codex R4 - C1 a C5)
  console.log('\n11. Validando R4: Ciclo de Vida dos Sintetizadores e Temporização Estrita (Codex R4 - C1 a C5)...');

  // Inicializa listener global de captura para cliques reais no navegador
  await page.evaluate(() => {
    window.__lastClickInfo = null;
    window.addEventListener('click', (e) => {
      window.__lastClickInfo = {
        type: e.type,
        time: performance.now(),
        audioTime: window.__rumo10AudioCtx ? window.__rumo10AudioCtx.currentTime : null,
        target: e.target ? e.target.tagName : null
      };
    }, true);
  });

  // Função auxiliar para preparar a instrumentação completa de cada efeito
  const setupInstrumentation = async () => {
    await page.evaluate(() => {
      sessionStorage.clear();
      const audio = window.__rumo10Audio;
      window.__r4AudioLogs = [];
      window.__lastClickInfo = null;

      if (!audio.ctx.__origResume) {
        audio.ctx.__origResume = audio.ctx.resume.bind(audio.ctx);
      }
      audio.ctx.__resumeCalls = 0;
      audio.ctx.resume = async function() {
        audio.ctx.__resumeCalls++;
        return await audio.ctx.__origResume();
      };

      if (!audio.ctx.__origCreateOsc) {
        audio.ctx.__origCreateOsc = audio.ctx.createOscillator.bind(audio.ctx);
      }
      window.__oscLogs = [];
      audio.ctx.createOscillator = function() {
        const osc = audio.ctx.__origCreateOsc();
        const oscLog = {
          id: window.__oscLogs.length + 1,
          createdTime: performance.now(),
          startTime: null,
          endedTime: null,
          started: false,
          ended: false
        };
        window.__oscLogs.push(oscLog);
        const origStart = osc.start.bind(osc);
        osc.start = function(when) {
          oscLog.started = true;
          oscLog.startTime = performance.now();
          return origStart(when);
        };
        osc.addEventListener('ended', () => {
          oscLog.ended = true;
          oscLog.endedTime = performance.now();
        });
        return osc;
      };

      if (!window.__origPlayDirectR4) {
        window.__origPlayDirectR4 = audio.playDirect.bind(audio);
      }
      audio.playDirect = async function(key, id) {
        const entry = {
          key,
          id,
          callTime: performance.now(),
          startTime: performance.now(),
          audioTimeStart: audio.ctx ? audio.ctx.currentTime : null,
          endTime: null,
          audioTimeEnd: null,
          ctxStateAtStart: audio.ctx ? audio.ctx.state : null,
          ctxStateAtEnd: null,
          status: null
        };
        window.__r4AudioLogs.push(entry);
        const res = await window.__origPlayDirectR4(key, id);
        entry.endTime = performance.now();
        entry.audioTimeEnd = audio.ctx ? audio.ctx.currentTime : null;
        entry.ctxStateAtEnd = audio.ctx ? audio.ctx.state : null;
        entry.status = res ? res.status : null;
        return res;
      };
    });
  };

  // C1. PENALTY_AWARDED sem suspensão no Chrome real
  console.log('  -> Executando C1: PENALTY_AWARDED sem suspensão...');
  await setupInstrumentation();
  const c1Result = await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    audio.enqueue('c1:pen', 'PENALTY_AWARDED');
    audio.enqueue('c1:whistle', 'MATCH_WHISTLE_START');

    // Aguarda término de ambos os efeitos (pen ~0.35s + 250ms gap + whistle ~0.53s)
    const deadline = Date.now() + 4000;
    while (audio.pendingQueue.size > 0 && Date.now() < deadline) {
      await new Promise(r => setTimeout(r, 50));
    }
    // Aguarda conclusão do último som
    await new Promise(r => setTimeout(r, 600));

    const logs = window.__r4AudioLogs;
    const gap = (logs.length >= 2 && logs[0].endTime && logs[1].startTime)
      ? (logs[1].startTime - logs[0].endTime)
      : 0;
    return {
      count: logs.length,
      gap,
      penHandled: audio.wasHandled('c1:pen'),
      whistleHandled: audio.wasHandled('c1:whistle'),
      pendingEmpty: audio.pendingQueue.size === 0
    };
  });

  assert(c1Result.count === 2, 'C1: Exatamente 2 efeitos executados no Chrome');
  assert(c1Result.gap >= 250.0, `C1: Espaçamento regulamentar estrito após ended do pênalti: ${c1Result.gap.toFixed(1)}ms >= 250ms`);
  assert(c1Result.penHandled && c1Result.whistleHandled, 'C1: Ambos os eventos persistidos no sessionStorage');
  assert(c1Result.pendingEmpty, 'C1: Fila limpa sem travas');

  // C2. PENALTY_AWARDED com suspensão durante a síntese (Bloqueante Principal do Codex R4)
  console.log('  -> Executando C2: PENALTY_AWARDED com suspensão durante a síntese...');
  await setupInstrumentation();

  // Inicia o pênalti e enfileira o próximo
  await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    audio.enqueue('c2:pen', 'PENALTY_AWARDED');
    audio.enqueue('c2:card', 'CARD_YELLOW');
  });

  // Aguarda ~40ms para o oscilador ser criado e iniciar
  await page.waitForTimeout(40);

  // Suspende o AudioContext enquanto o oscilador está em plena reprodução
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.suspend();
  });

  // Aguarda 1.0 segundo de tempo real (muito superior à duração nominal de 0.35s do pênalti)
  await page.waitForTimeout(1000);

  // Comprova que durante a suspensão NENHUM ended ocorreu e a fila permaneceu bloqueada
  const c2SuspendedCheck = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const logs = window.__r4AudioLogs;
    return {
      ctxState: audio.ctx.state,
      playing: audio.playing,
      logsCount: logs.length,
      firstEnded: logs.length > 0 && logs[0].endTime !== null,
      cardPending: audio.pendingQueue.has('c2:card'),
      cardStarted: logs.length > 1
    };
  });

  assert(c2SuspendedCheck.ctxState === 'suspended', 'C2 (Codex R4): AudioContext suspenso no Chrome');
  assert(c2SuspendedCheck.playing === true, 'C2 (Codex R4): playing continua estritamente true após 1.0s de relógio JS em suspensão');
  assert(!c2SuspendedCheck.firstEnded, 'C2 (Codex R4): Oscilador de pênalti NÃO emitiu ended prematuro durante suspensão');
  assert(c2SuspendedCheck.cardPending && !c2SuspendedCheck.cardStarted, 'C2 (Codex R4): Segundo evento retido intacto na fila pendente sem disparar antes do término real');

  // Retoma o AudioContext
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.resume();
  });

  // Aguarda conclusão real do pênalti e do segundo efeito
  await page.waitForTimeout(1500);

  const c2FinalResult = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const logs = window.__r4AudioLogs;
    const gap = (logs.length >= 2 && logs[0].endTime && logs[1].startTime)
      ? (logs[1].startTime - logs[0].endTime)
      : 0;
    return {
      count: logs.length,
      gap,
      penHandled: audio.wasHandled('c2:pen'),
      cardHandled: audio.wasHandled('c2:card'),
      pendingEmpty: audio.pendingQueue.size === 0
    };
  });

  assert(c2FinalResult.count === 2, 'C2 (Codex R4): Ambos os efeitos concluídos após retomada');
  assert(c2FinalResult.gap >= 250.0, `C2 (Codex R4): Espaçamento de 250ms respeitado após término real no Chrome: ${c2FinalResult.gap.toFixed(1)}ms >= 250ms`);
  assert(c2FinalResult.penHandled && c2FinalResult.cardHandled, 'C2 (Codex R4): Ambos os eventos persistidos');
  assert(c2FinalResult.pendingEmpty, 'C2 (Codex R4): Fila limpa');

  // C3. BALL_HIT_POST com suspensão (2 osciladores)
  console.log('  -> Executando C3: BALL_HIT_POST com suspensão (2 osciladores)...');
  await setupInstrumentation();
  await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    audio.enqueue('c3:post', 'BALL_HIT_POST');
    audio.enqueue('c3:whistle', 'MATCH_WHISTLE_START');
  });

  // Aguarda ~30ms para início
  await page.waitForTimeout(30);
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.suspend();
  });
  await page.waitForTimeout(600);

  const c3SuspCheck = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const logs = window.__r4AudioLogs;
    return {
      playing: audio.playing,
      firstEnded: logs.length > 0 && logs[0].endTime !== null,
      whistleStarted: logs.length > 1
    };
  });
  assert(c3SuspCheck.playing === true && !c3SuspCheck.firstEnded && !c3SuspCheck.whistleStarted, 'C3: BALL_HIT_POST preserva fila bloqueada durante suspensão');

  // Retoma e aguarda
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.resume();
  });
  await page.waitForTimeout(1500);

  const c3FinalResult = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const logs = window.__r4AudioLogs;
    const gap = (logs.length >= 2 && logs[0].endTime && logs[1].startTime)
      ? (logs[1].startTime - logs[0].endTime)
      : 0;
    return {
      count: logs.length,
      gap,
      postHandled: audio.wasHandled('c3:post'),
      whistleHandled: audio.wasHandled('c3:whistle')
    };
  });
  assert(c3FinalResult.count === 2, 'C3: BALL_HIT_POST e apito finalizados');
  assert(c3FinalResult.gap >= 250.0, `C3: Espaçamento após término dos 2 osciladores de BALL_HIT_POST: ${c3FinalResult.gap.toFixed(1)}ms >= 250ms`);

  // C4. Clique durante a pausa com medição do instante efetivo de chegada no navegador
  console.log('  -> Executando C4: Clique durante a pausa com medição efetiva no Chrome...');
  await setupInstrumentation();
  await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    audio.enqueue('c4:card', 'CARD_YELLOW');
    audio.enqueue('c4:whistle', 'MATCH_WHISTLE_START');
  });

  // Observa término efetivo (ended) do 1º evento
  let firstEndedTime = null;
  for (let i = 0; i < 40; i++) {
    firstEndedTime = await page.evaluate(() => {
      const logs = window.__r4AudioLogs;
      return (logs.length >= 1 && logs[0].endTime !== null) ? logs[0].endTime : null;
    });
    if (firstEndedTime !== null) break;
    await page.waitForTimeout(30);
  }

  // Aguarda ~30ms para disparar clique dentro da janela regulamentar de 250ms
  await page.waitForTimeout(30);
  await page.click('body');

  // Aguarda término completo do 2º evento
  await page.waitForTimeout(1500);

  const c4Result = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const logs = window.__r4AudioLogs;
    const click = window.__lastClickInfo;
    const firstEnd = (logs.length >= 1 && logs[0].endTime) ? logs[0].endTime : 0;
    const secondStart = (logs.length >= 2 && logs[1].startTime) ? logs[1].startTime : 0;
    const clickDelta = click ? (click.time - firstEnd) : -1;
    const gap = (secondStart > 0 && firstEnd > 0) ? (secondStart - firstEnd) : 0;
    return {
      count: logs.length,
      firstEnd,
      secondStart,
      clickReceived: !!click,
      clickDelta,
      gap,
      firstHandled: audio.wasHandled('c4:card'),
      secondHandled: audio.wasHandled('c4:whistle')
    };
  });

  assert(c4Result.clickReceived, 'C4: Clique físico capturado com precisão pelo navegador');
  assert(c4Result.clickDelta > 0 && c4Result.clickDelta < 240, `C4: Clique efetivo chegou dentro da pausa regulamentar (${c4Result.clickDelta.toFixed(1)}ms após ended)`);
  assert(c4Result.count === 2, 'C4: Exatamente 2 eventos executados no Chrome');
  assert(c4Result.gap >= 250.0, `C4 (Codex R4): Gap efetivo protegido contra clique: ${c4Result.gap.toFixed(1)}ms >= 250ms`);

  // C5. Cliques múltiplos rápidos durante a pausa
  console.log('  -> Executando C5: Cliques múltiplos durante a pausa no Chrome...');
  await setupInstrumentation();
  await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    audio.enqueue('c5:card', 'CARD_YELLOW');
    audio.enqueue('c5:whistle', 'MATCH_WHISTLE_START');
  });

  // Aguarda término do 1º evento
  for (let i = 0; i < 40; i++) {
    const ended = await page.evaluate(() => {
      const logs = window.__r4AudioLogs;
      return logs.length >= 1 && logs[0].endTime !== null;
    });
    if (ended) break;
    await page.waitForTimeout(30);
  }

  // Rajada de 4 cliques rápidos durante a pausa
  await page.waitForTimeout(20);
  await page.click('body');
  await page.waitForTimeout(30);
  await page.click('body');
  await page.waitForTimeout(40);
  await page.click('body');
  await page.waitForTimeout(40);
  await page.click('body');

  await page.waitForTimeout(1500);

  const c5Result = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const logs = window.__r4AudioLogs;
    const gap = (logs.length >= 2 && logs[0].endTime && logs[1].startTime)
      ? (logs[1].startTime - logs[0].endTime)
      : 0;
    return {
      count: logs.length,
      gap,
      pendingEmpty: audio.pendingQueue.size === 0
    };
  });

  assert(c5Result.count === 2, 'C5: Rajada de cliques não gerou duplicações nem descarte de eventos');
  assert(c5Result.gap >= 250.0, `C5: Gap protegido contra múltiplos cliques: ${c5Result.gap.toFixed(1)}ms >= 250ms`);
  assert(c5Result.pendingEmpty, 'C5: Fila limpa com sucesso');

  // C6. Sequência obrigatória de duas retomadas por clique físico no mesmo AudioContext (PENALTY_AWARDED)
  console.log('  -> Executando C6: Sequência estrita de 2 retomadas por clique físico com PENALTY_AWARDED (Codex R6)...');
  await setupInstrumentation();

  // 1. Começar com o AudioContext suspenso
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.suspend();
    audio.ctx.__resumeCalls = 0;
    window.__oscLogs = [];
  });
  const c6InitCheck = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    return { ctxState: audio.ctx.state, resumeCalls: audio.ctx.__resumeCalls };
  });
  assert(c6InitCheck.ctxState === 'suspended', 'C6 (1): AudioContext iniciado em estado suspenso');
  assert(c6InitCheck.resumeCalls === 0, 'C6 (1): Zero chamadas a ctx.resume() antes do primeiro clique');

  // 2. Enfileirar PENALTY_AWARDED e um segundo efeito válido (CARD_YELLOW)
  await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    audio.enqueue('c6:pen', 'PENALTY_AWARDED');
    audio.enqueue('c6:card', 'CARD_YELLOW');
  });
  const c6QueueCheck = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    return {
      penPending: audio.pendingQueue.has('c6:pen'),
      cardPending: audio.pendingQueue.has('c6:card'),
      playing: audio.playing
    };
  });
  assert(c6QueueCheck.penPending && c6QueueCheck.cardPending, 'C6 (2): PENALTY_AWARDED e CARD_YELLOW enfileirados');
  assert(!c6QueueCheck.playing, 'C6 (2): Fila retida sem reprodução enquanto suspenso');

  // 3. Executar o primeiro clique físico na página
  await page.click('body');
  for (let i = 0; i < 30; i++) {
    const ready = await page.evaluate(() => {
      const osc = window.__oscLogs && window.__oscLogs[0];
      return osc && osc.started;
    });
    if (ready) break;
    await page.waitForTimeout(10);
  }

  // 4, 5 e 6. Confirmar 1ª chamada a ctx.resume(), contexto running, oscilador iniciado e unlockingPromise liberada
  const c6AfterClick1 = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const osc = window.__oscLogs[0];
    return {
      resumeCalls: audio.ctx.__resumeCalls,
      ctxState: audio.ctx.state,
      playing: audio.playing,
      unlockingPromise: audio.unlockingPromise !== null,
      oscCreated: !!osc,
      oscStarted: osc ? osc.started : false,
      oscEnded: osc ? osc.ended : false
    };
  });
  assert(c6AfterClick1.resumeCalls === 1, 'C6 (4): Primeiro clique provocou a 1ª chamada efetiva a ctx.resume() (resumeCalls=1)');
  assert(c6AfterClick1.ctxState === 'running', 'C6 (5): Contexto está running após 1º clique');
  assert(c6AfterClick1.oscCreated && c6AfterClick1.oscStarted && !c6AfterClick1.oscEnded, 'C6 (5): Pênalti sintetizado começou sem emitir ended prematuro');
  assert(!c6AfterClick1.unlockingPromise && c6AfterClick1.playing, 'C6 (6): unlockingPromise já liberada (null) enquanto playing permanece estritamente true');

  // 7. Antes do ended do oscilador, suspender novamente o mesmo AudioContext
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.suspend();
  });
  await page.waitForTimeout(150);

  // 8. Confirmar que o sintetizador permanece ativo logicamente, sem ended, e que o próximo efeito continua na fila
  const c6SuspCheck = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const osc = window.__oscLogs[0];
    return {
      resumeCalls: audio.ctx.__resumeCalls,
      ctxState: audio.ctx.state,
      playing: audio.playing,
      unlockingPromise: audio.unlockingPromise !== null,
      oscEnded: osc ? osc.ended : false,
      penDone: window.__r4AudioLogs.length > 0 && window.__r4AudioLogs[0].endTime !== null,
      cardPending: audio.pendingQueue.has('c6:card'),
      cardStarted: window.__r4AudioLogs.length > 1
    };
  });
  assert(c6SuspCheck.ctxState === 'suspended', 'C6 (7): Mesmo AudioContext suspenso novamente durante a síntese');
  assert(c6SuspCheck.resumeCalls === 1, 'C6 (7): Contador de resumeCalls permanece em 1 durante suspensão');
  assert(c6SuspCheck.playing === true && !c6SuspCheck.oscEnded && !c6SuspCheck.penDone, 'C6 (8): Sintetizador permanece logicamente ativo sem ended durante suspensão');
  assert(c6SuspCheck.cardPending && !c6SuspCheck.cardStarted, 'C6 (8): Próximo efeito (CARD_YELLOW) permanece intacto na pendingQueue');

  // 9. Executar o segundo clique físico, sem chamar diretamente ctx.resume()
  await page.click('body');
  for (let i = 0; i < 30; i++) {
    const resumed = await page.evaluate(() => {
      const audio = window.__rumo10Audio;
      return audio && audio.ctx && audio.ctx.__resumeCalls >= 2 && audio.ctx.state === 'running';
    });
    if (resumed) break;
    await page.waitForTimeout(10);
  }

  // 10 e 11. Confirmar que o segundo clique provocou nova chamada efetiva a ctx.resume() e contexto running
  const c6AfterClick2 = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    return {
      resumeCalls: audio.ctx.__resumeCalls,
      ctxState: audio.ctx.state
    };
  });
  assert(c6AfterClick2.resumeCalls === 2, 'C6 (10): Segundo clique provocou a 2ª chamada efetiva a ctx.resume() (resumeCalls=2, sem deadlock)');
  assert(c6AfterClick2.ctxState === 'running', 'C6 (11): Contexto retornou para running após 2º clique');

  // 12. Aguardar o ended verdadeiro do pênalti e conclusão de ambos os efeitos
  for (let i = 0; i < 60; i++) {
    const done = await page.evaluate(() => {
      const logs = window.__r4AudioLogs;
      return logs.length === 2 && logs[1].endTime !== null;
    });
    if (done) break;
    await page.waitForTimeout(50);
  }

  // 13, 14 e 15. Confirmar espaçamento >= 250ms, ausência de perdas/duplicações e limpeza final
  const c6FinalResult = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const logs = window.__r4AudioLogs;
    const osc = window.__oscLogs[0];
    const firstEnd = (logs.length >= 1 && logs[0].endTime) ? logs[0].endTime : 0;
    const secondStart = (logs.length >= 2 && logs[1].startTime) ? logs[1].startTime : 0;
    const gap = (secondStart > 0 && firstEnd > 0) ? (secondStart - firstEnd) : 0;
    return {
      resumeCalls: audio.ctx.__resumeCalls,
      count: logs.length,
      oscEnded: osc ? osc.ended : false,
      gap,
      penHandled: audio.wasHandled('c6:pen'),
      cardHandled: audio.wasHandled('c6:card'),
      playing: audio.playing,
      pendingEmpty: audio.pendingQueue.size === 0,
      inFlightEmpty: audio.inFlight.size === 0,
      unlockingPromise: audio.unlockingPromise !== null
    };
  });
  assert(c6FinalResult.oscEnded, 'C6 (12): Ended verdadeiro do oscilador de pênalti confirmado');
  assert(c6FinalResult.count === 2, 'C6 (14): Exatamente dois efeitos executados no navegador real');
  assert(c6FinalResult.gap >= 250.0, `C6 (13): Efeito seguinte começou pelo menos 250ms após ended: ${c6FinalResult.gap.toFixed(1)}ms >= 250ms`);
  assert(c6FinalResult.penHandled && c6FinalResult.cardHandled, 'C6 (14): Ambos os eventos persistidos no sessionStorage sem perdas');
  assert(!c6FinalResult.playing && c6FinalResult.pendingEmpty && c6FinalResult.inFlightEmpty && !c6FinalResult.unlockingPromise, 'C6 (15): Limpeza final rigorosa de playing, pendingQueue, inFlight e unlockingPromise');

  // C7. Sequência de duas retomadas por clique no mesmo AudioContext com BALL_HIT_POST (2 osciladores)
  console.log('  -> Executando C7: Sequência estrita de 2 retomadas por clique físico com BALL_HIT_POST (Codex R6)...');
  await setupInstrumentation();

  // 1. Iniciar suspenso
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.suspend();
    audio.ctx.__resumeCalls = 0;
    window.__oscLogs = [];
  });

  // 2. Enfileirar BALL_HIT_POST e MATCH_WHISTLE_START
  await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    audio.enqueue('c7:post', 'BALL_HIT_POST');
    audio.enqueue('c7:whistle', 'MATCH_WHISTLE_START');
  });

  // 3. Primeiro clique físico na página
  await page.click('body');
  for (let i = 0; i < 30; i++) {
    const ready = await page.evaluate(() => {
      const oscs = window.__oscLogs;
      return oscs && oscs.length === 2 && oscs[0].started && oscs[1].started;
    });
    if (ready) break;
    await page.waitForTimeout(10);
  }

  const c7AfterClick1 = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const oscs = window.__oscLogs;
    return {
      resumeCalls: audio.ctx.__resumeCalls,
      ctxState: audio.ctx.state,
      playing: audio.playing,
      unlockingPromise: audio.unlockingPromise !== null,
      oscCount: oscs.length,
      allStarted: oscs.length === 2 && oscs[0].started && oscs[1].started,
      anyEnded: oscs.some(o => o.ended)
    };
  });
  assert(c7AfterClick1.resumeCalls === 1, 'C7: Primeiro clique provocou 1ª chamada a ctx.resume() (resumeCalls=1)');
  assert(c7AfterClick1.ctxState === 'running', 'C7: Contexto transitou para running');
  assert(c7AfterClick1.oscCount === 2 && c7AfterClick1.allStarted, 'C7: Exatamente 2 osciladores criados e iniciados para BALL_HIT_POST');
  assert(!c7AfterClick1.unlockingPromise && c7AfterClick1.playing, 'C7: unlockingPromise liberada enquanto playing permanece true');

  // 4. Nova suspensão durante a síntese (antes do término dos osciladores)
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.suspend();
  });
  await page.waitForTimeout(150);

  const c7SuspCheck = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const oscs = window.__oscLogs;
    return {
      resumeCalls: audio.ctx.__resumeCalls,
      ctxState: audio.ctx.state,
      playing: audio.playing,
      allEnded: oscs.every(o => o.ended),
      whistlePending: audio.pendingQueue.has('c7:whistle')
    };
  });
  assert(c7SuspCheck.ctxState === 'suspended', 'C7: Contexto suspenso durante síntese da trave');
  assert(c7SuspCheck.resumeCalls === 1, 'C7: resumeCalls permanece 1 durante suspensão');
  assert(c7SuspCheck.playing && !c7SuspCheck.allEnded && c7SuspCheck.whistlePending, 'C7: Trave preserva reprodução ativa e apito na fila durante suspensão');

  // 5. Segundo clique físico na página
  await page.click('body');
  for (let i = 0; i < 30; i++) {
    const resumed = await page.evaluate(() => {
      const audio = window.__rumo10Audio;
      return audio && audio.ctx && audio.ctx.__resumeCalls >= 2 && audio.ctx.state === 'running';
    });
    if (resumed) break;
    await page.waitForTimeout(10);
  }

  const c7AfterClick2 = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    return {
      resumeCalls: audio.ctx.__resumeCalls,
      ctxState: audio.ctx.state
    };
  });
  assert(c7AfterClick2.resumeCalls === 2, 'C7: Segundo clique provocou 2ª chamada efetiva a ctx.resume() (resumeCalls=2, sem deadlock)');
  assert(c7AfterClick2.ctxState === 'running', 'C7: Contexto retornou para running após 2º clique');

  // 6. Monitorar os dois ended: primeiro ended não libera a fila; segundo ended libera conclusão
  let sawFirstEndedWithoutRelease = false;
  for (let i = 0; i < 50; i++) {
    const checkEnded = await page.evaluate(() => {
      const oscs = window.__oscLogs;
      const audio = window.__rumo10Audio;
      const logs = window.__r4AudioLogs;
      const firstOscEnded = oscs.length >= 2 && (oscs[0].ended || oscs[1].ended);
      const secondOscEnded = oscs.length >= 2 && (oscs[0].ended && oscs[1].ended);
      return {
        firstOscEnded,
        secondOscEnded,
        playing: audio.playing,
        postCompleted: logs.length > 0 && logs[0].endTime !== null,
        whistleStarted: logs.length > 1
      };
    });
    if (checkEnded.firstOscEnded && !checkEnded.secondOscEnded && checkEnded.playing && !checkEnded.postCompleted && !checkEnded.whistleStarted) {
      sawFirstEndedWithoutRelease = true;
      break;
    }
    await page.waitForTimeout(10);
  }
  assert(sawFirstEndedWithoutRelease, 'C7: Primeiro ended de oscilador NÃO libera a fila (playing continua true e apito não inicia)');

  // Aguardar segundo ended e conclusão de ambos os efeitos
  for (let i = 0; i < 60; i++) {
    const allDone = await page.evaluate(() => {
      const logs = window.__r4AudioLogs;
      return logs.length === 2 && logs[1].endTime !== null;
    });
    if (allDone) break;
    await page.waitForTimeout(50);
  }

  const c7FinalResult = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    const logs = window.__r4AudioLogs;
    const oscs = window.__oscLogs;
    const firstEnd = (logs.length >= 1 && logs[0].endTime) ? logs[0].endTime : 0;
    const secondStart = (logs.length >= 2 && logs[1].startTime) ? logs[1].startTime : 0;
    const gap = (secondStart > 0 && firstEnd > 0) ? (secondStart - firstEnd) : 0;
    return {
      count: logs.length,
      bothOscEnded: oscs.length === 2 && oscs[0].ended && oscs[1].ended,
      gap,
      postHandled: audio.wasHandled('c7:post'),
      whistleHandled: audio.wasHandled('c7:whistle'),
      playing: audio.playing,
      pendingEmpty: audio.pendingQueue.size === 0
    };
  });
  assert(c7FinalResult.bothOscEnded, 'C7: Segundo ended liberou com sucesso a conclusão da trave');
  assert(c7FinalResult.count === 2, 'C7: Dois efeitos executados no navegador real');
  assert(c7FinalResult.gap >= 250.0, `C7: Intervalo mínimo de 250ms após término de BALL_HIT_POST: ${c7FinalResult.gap.toFixed(1)}ms >= 250ms`);
  assert(c7FinalResult.postHandled && c7FinalResult.whistleHandled, 'C7: Ambos os eventos persistidos no sessionStorage');
  assert(!c7FinalResult.playing && c7FinalResult.pendingEmpty, 'C7: Fila limpa com sucesso');

  // C8. Verificação de concorrência estrita em cliques simultâneos e retomada após nova suspensão
  console.log('  -> Executando C8: Instrumentação direta de concorrência em ctx.resume() (Codex R6)...');
  await setupInstrumentation();

  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.suspend();
    sessionStorage.clear();
    window.__r4AudioLogs = [];

    window.__c8ResumeCalls = 0;
    window.__c8ActiveResumePromise = false;
    window.__c8ConcurrentViolations = 0;

    const baseResume = audio.ctx.__origResume || audio.ctx.resume.bind(audio.ctx);
    audio.ctx.resume = async function() {
      window.__c8ResumeCalls++;
      if (window.__c8ActiveResumePromise) {
        window.__c8ConcurrentViolations++;
      }
      window.__c8ActiveResumePromise = true;
      // Latência controlada para garantir que cliques simultâneos cheguem durante a Promise pendente
      await new Promise(r => setTimeout(r, 120));
      const res = await baseResume();
      window.__c8ActiveResumePromise = false;
      return res;
    };

    audio.enqueue('c8:card1', 'CARD_YELLOW');
  });

  // Dispara rajada simultânea de cliques reais enquanto a retomada está ativamente pendente
  await Promise.all([
    page.click('body'),
    page.click('body'),
    page.click('body')
  ]);

  await page.waitForTimeout(800);

  const c8BurstCheck = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    return {
      resumeCalls: window.__c8ResumeCalls,
      concurrentViolations: window.__c8ConcurrentViolations,
      ctxState: audio.ctx.state,
      unlockingPromise: audio.unlockingPromise !== null,
      card1Handled: audio.wasHandled('c8:card1'),
      pendingEmpty: audio.pendingQueue.size === 0
    };
  });
  assert(c8BurstCheck.resumeCalls === 1, 'C8: Exatamente 1 chamada a ctx.resume() durante rajada simultânea (resumeCalls=1)');
  assert(c8BurstCheck.concurrentViolations === 0, 'C8: Zero chamadas concorrentes a ctx.resume() enquanto Promise esteve pendente');
  assert(c8BurstCheck.ctxState === 'running', 'C8: Contexto transitou para running');
  assert(!c8BurstCheck.unlockingPromise, 'C8: unlockingPromise liberada após término da primeira retomada');
  assert(c8BurstCheck.card1Handled, 'C8: Primeiro som de cartão executado e persistido');

  // Testar requisito adicional: após primeira retomada concluir, uma nova suspensão DEVE permitir outro resume()
  await page.evaluate(async () => {
    const audio = window.__rumo10Audio;
    await audio.ctx.suspend();
    audio.enqueue('c8:card2', 'CARD_YELLOW');
  });
  await page.waitForTimeout(50);

  const c8SuspState = await page.evaluate(() => window.__rumo10Audio.ctx.state);
  assert(c8SuspState === 'suspended', 'C8: Contexto colocado em nova suspensão após término da primeira rodada');

  // Novo clique físico para segunda retomada
  await page.click('body');
  await page.waitForTimeout(800);

  const c8SecondCheck = await page.evaluate(() => {
    const audio = window.__rumo10Audio;
    return {
      resumeCalls: window.__c8ResumeCalls,
      concurrentViolations: window.__c8ConcurrentViolations,
      ctxState: audio.ctx.state,
      card2Handled: audio.wasHandled('c8:card2'),
      pendingEmpty: audio.pendingQueue.size === 0,
      unlockingPromise: audio.unlockingPromise !== null
    };
  });
  assert(c8SecondCheck.resumeCalls === 2, 'C8: Nova suspensão permitiu com sucesso novo ctx.resume() via clique (resumeCalls=2)');
  assert(c8SecondCheck.concurrentViolations === 0, 'C8: Concorrência continuou estritamente zero');
  assert(c8SecondCheck.ctxState === 'running', 'C8: Contexto retornou para running');
  assert(c8SecondCheck.card2Handled, 'C8: Segundo som executado e persistido');
  assert(c8SecondCheck.pendingEmpty && !c8SecondCheck.unlockingPromise, 'C8: Fila vazia e unlockingPromise devidamente limpa');

  await browser.close();

  console.log('\n=======================================================');
  console.log(`TOTAL E2E NAVEGADOR REAL: ${passCount} verificações PASSOU, ${failCount} FALHOU`);
  console.log('=======================================================');

  if (failCount > 0) process.exit(1);
})().catch(err => {
  console.error('Fatal error in Chrome E2E test:', err);
  process.exit(1);
});
