'use strict';

/**
 * R10-AUD-001 | P0-3: Testes determinísticos do Web Audio Engine
 * Valida o motor de áudio em Node.js com mocks completos de AudioContext, AudioBufferSourceNode,
 * agendamento de sobreposição (overlapping whistles), prioridade, fila concorrente e deduplicação.
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let passCount = 0;
let failCount = 0;

function assert(condition, message) {
  if (condition) {
    console.log(`  [PASS] ${message}`);
    passCount++;
  } else {
    console.error(`  [FAIL] ${message}`);
    failCount++;
    throw new Error(`Assertion failed: ${message}`);
  }
}

// ---------------------------------------------------------------------------
// Mock Environment Factory
// ---------------------------------------------------------------------------

function createMockEnvironment() {
  const startedSources = [];
  const createdGains = [];
  const sessionData = new Map();
  const localData = new Map();

  class MockAudioBuffer {
    constructor(duration = 2.0) {
      this.duration = duration;
      this.numberOfChannels = 2;
      this.sampleRate = 48000;
    }
  }

  const dummyBuffers = {
    'match_whistle.ogg': new MockAudioBuffer(0.54),
    'goal.ogg': new MockAudioBuffer(2.8),
    'assist.ogg': new MockAudioBuffer(2.8),
    'yellow_card.ogg': new MockAudioBuffer(0.54),
    'red_card.ogg': new MockAudioBuffer(2.5),
    'substitution_in.ogg': new MockAudioBuffer(2.8),
    'substitution_out.ogg': new MockAudioBuffer(2.5),
  };

  class MockAudioBufferSourceNode {
    constructor(ctx) {
      this.ctx = ctx;
      this.buffer = null;
      this.onended = null;
      this.startTime = null;
      this.connectedTo = null;
      this.id = startedSources.length + 1;
    }
    connect(dest) {
      this.connectedTo = dest;
    }
    disconnect() {
      this.connectedTo = null;
    }
    start(time) {
      this.startTime = time;
      startedSources.push({
        id: this.id,
        buffer: this.buffer,
        time: time,
        source: this
      });
    }
    triggerEnd() {
      if (typeof this.onended === 'function') {
        this.onended();
      }
    }
  }

  class MockGainNode {
    constructor(ctx) {
      this.ctx = ctx;
      this.gain = {
        value: 1.0,
        setTargetAtTime: (val, time, constant) => {
          this.gain.value = val;
        }
      };
      this.connectedTo = null;
      createdGains.push(this);
    }
    connect(dest) {
      this.connectedTo = dest;
    }
    disconnect() {
      this.connectedTo = null;
    }
  }

  let lastFetchedFile = null;

  class MockAudioContext {
    constructor() {
      this.state = 'running';
      this.currentTime = 10.0;
      this.destination = { id: 'speakers' };
    }
    createBufferSource() {
      return new MockAudioBufferSourceNode(this);
    }
    createGain() {
      return new MockGainNode(this);
    }
    decodeAudioData(bytes) {
      const buf = dummyBuffers[lastFetchedFile] || new MockAudioBuffer(2.5);
      return Promise.resolve(buf);
    }
    async resume() {
      this.state = 'running';
      return Promise.resolve();
    }
  }

  const mockDomElements = [];

  const mockWindow = {
    AudioContext: MockAudioContext,
    webkitAudioContext: MockAudioContext,
    __rumo10AudioCtx: null,
    __rumo10Audio: null,
    localStorage: {
      getItem: (k) => localData.get(k) || null,
      setItem: (k, v) => localData.set(k, String(v)),
      removeItem: (k) => localData.delete(k),
      clear: () => localData.clear()
    },
    sessionStorage: {
      getItem: (k) => sessionData.get(k) || null,
      setItem: (k, v) => sessionData.set(k, String(v)),
      removeItem: (k) => sessionData.delete(k),
      clear: () => sessionData.clear()
    },
    addEventListener: () => {},
    fetch: async (url) => {
      const filename = path.basename(url);
      if (dummyBuffers[filename]) {
        lastFetchedFile = filename;
        return {
          ok: true,
          arrayBuffer: async () => Buffer.from('OggS-dummy-audio-bytes')
        };
      }
      return {
        ok: false,
        status: 404,
        statusText: 'Not Found'
      };
    },
    document: {
      readyState: 'complete',
      addEventListener: () => {},
      querySelector: (sel) => null,
      querySelectorAll: (sel) => {
        if (sel === '[data-sound-event][data-sound-event-id]') {
          return mockDomElements;
        }
        return [];
      }
    },
    setTimeout: (fn, ms) => {
      // In synchronous tests, run immediately or allow controlled stepping
      return setTimeout(fn, 1);
    }
  };

  mockWindow.window = mockWindow;

  // Load and execute audio-engine.js inside mock sandbox
  const engineCode = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'audio-engine.js'), 'utf8');

  function runScript(extraElements = []) {
    mockDomElements.length = 0;
    mockDomElements.push(...extraElements);
    vm.runInNewContext(engineCode, mockWindow);
    return mockWindow.__rumo10Audio;
  }

  return {
    mockWindow,
    runScript,
    startedSources,
    createdGains,
    sessionData,
    localData,
    mockDomElements,
    dummyBuffers
  };
}

// ---------------------------------------------------------------------------
// Test Execution
// ---------------------------------------------------------------------------

async function runTestSuite() {
  console.log('=== R10-AUD-001 | P0-3: TESTES DETERMINÍSTICOS DO WEB AUDIO ENGINE ===\n');

  // 1. Apito de início (1x)
  console.log('1. Validando Apito de Início (MATCH_WHISTLE_START, 1 disparo)...');
  {
    const env = createMockEnvironment();
    const audio = env.runScript();
    audio.cachedBuffers.set('match_whistle.ogg', Promise.resolve(env.dummyBuffers['match_whistle.ogg']));

    env.startedSources.length = 0;
    const playPromise = audio.playDirect('MATCH_WHISTLE_START');
    await new Promise(r => setImmediate(r));
    assert(env.startedSources.length === 1, 'Exatamente 1 chamada a source.start para apito de início');
    const s0 = env.startedSources[0];
    assert(s0.time === env.mockWindow.__rumo10AudioCtx.currentTime + 0.02, 'Disparo agendado no currentTime correto');
    assert(s0.buffer === env.dummyBuffers['match_whistle.ogg'], 'Usa buffer de match_whistle.ogg');
    s0.source.triggerEnd();
    await playPromise;
  }

  // 2. Apito de intervalo (2x sobrepostos em t0 e t0+180ms com mesmo buffer)
  console.log('\n2. Validando Apito de Intervalo (MATCH_HALFTIME, 2 disparos sobrepostos)...');
  {
    const env = createMockEnvironment();
    const audio = env.runScript();
    const whistleBuffer = env.dummyBuffers['match_whistle.ogg'];
    audio.cachedBuffers.set('match_whistle.ogg', Promise.resolve(whistleBuffer));

    env.startedSources.length = 0;
    const playPromise = audio.playDirect('MATCH_HALFTIME');
    await new Promise(r => setImmediate(r));
    assert(env.startedSources.length === 2, 'Exatamente 2 instâncias criadas e disparadas para intervalo');

    const [s1, s2] = env.startedSources;
    assert(s1.buffer === s2.buffer && s1.buffer === whistleBuffer, 'As 2 chamadas utilizam EXATAMENTE o mesmo AudioBuffer');
    const delta = Math.round((s2.time - s1.time) * 1000) / 1000;
    assert(delta === 0.18, `Segundo disparo inicia em t0 + 0.18s (${delta}s aferido), sem esperar término do anterior`);

    // Termina ambos para resolver promise
    s1.source.triggerEnd();
    s2.source.triggerEnd();
    await playPromise;
  }

  // 3. Apito de fim de jogo (3x sobrepostos em t0, t0+180ms e t0+360ms com mesmo buffer)
  console.log('\n3. Validando Apito de Fim (MATCH_WHISTLE_END, 3 disparos sobrepostos)...');
  {
    const env = createMockEnvironment();
    const audio = env.runScript();
    const whistleBuffer = env.dummyBuffers['match_whistle.ogg'];
    audio.cachedBuffers.set('match_whistle.ogg', Promise.resolve(whistleBuffer));

    env.startedSources.length = 0;
    const playPromise = audio.playDirect('MATCH_WHISTLE_END');
    await new Promise(r => setImmediate(r));
    assert(env.startedSources.length === 3, 'Exatamente 3 instâncias criadas e disparadas para fim de partida');

    const [s1, s2, s3] = env.startedSources;
    assert(s1.buffer === s2.buffer && s2.buffer === s3.buffer && s1.buffer === whistleBuffer, 'Todas as 3 chamadas usam EXATAMENTE o mesmo AudioBuffer');
    const delta1 = Math.round((s2.time - s1.time) * 1000) / 1000;
    const delta2 = Math.round((s3.time - s1.time) * 1000) / 1000;
    assert(delta1 === 0.18, `Segundo disparo em t0 + 0.18s (${delta1}s aferido)`);
    assert(delta2 === 0.36, `Terceiro disparo em t0 + 0.36s (${delta2}s aferido)`);

    s1.source.triggerEnd();
    s2.source.triggerEnd();
    s3.source.triggerEnd();
    await playPromise;
  }

  // 4. Disputa de Prioridade e Fila Concorrente (Gol no minuto 90 + Apito Final)
  console.log('\n4. Validando P0-2: Fila de Gol e Apito Final na mesma página (sem descarte do gol)...');
  {
    const env = createMockEnvironment();
    const elements = [
      {
        dataset: { soundEvent: 'MATCH_WHISTLE_END', soundEventId: 'test:m90:whistle:end' }
      },
      {
        dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'test:m90:goal:1' }
      }
    ];

    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    // O gol (prioridade 100) deve iniciar imediatamente
    assert(env.startedSources.length >= 1, 'Primeiro som da fila inicia imediatamente');
    const firstPlayed = env.startedSources[0];
    assert(firstPlayed.buffer === env.dummyBuffers['goal.ogg'], 'GOAL_SCORED tem prioridade máxima e toca PRIMEIRO');

    // O apito final NÃO foi descartado permanentemente: ele está na pendingQueue
    assert(audio.pendingQueue.has('test:m90:whistle:end'), 'MATCH_WHISTLE_END preservado na fila para reprodução subsequente');

    // Quando a comemoração do gol termina (source.onended)
    firstPlayed.source.triggerEnd();

    // Aguardar transição na fila para o apito
    await new Promise(r => setTimeout(r, 300));

    // Agora o apito final foi disparado!
    const whistleSources = env.startedSources.filter(s => s.buffer === env.dummyBuffers['match_whistle.ogg']);
    assert(whistleSources.length === 3, 'MATCH_WHISTLE_END entra em playback após o gol com suas 3 instâncias');
    assert(env.sessionData.get('rumo10_sound_played:test:m90:goal:1') === '1', 'Gol registrado como tocado no sessionStorage');

    whistleSources.forEach(s => s.source.triggerEnd());
    await new Promise(r => setTimeout(r, 50));
    assert(env.sessionData.get('rumo10_sound_played:test:m90:whistle:end') === '1', 'Apito final registrado como tocado no sessionStorage');
  }

  // 5. Deduplicação em Refresh (F5)
  console.log('\n5. Validando Deduplicação no Reload (F5)...');
  {
    const env = createMockEnvironment();
    // Marcar previamente no sessionStorage que os eventos já foram ouvidos
    env.sessionData.set('rumo10_sound_played:test:m90:goal:1', '1');
    env.sessionData.set('rumo10_sound_played:test:m90:whistle:end', '1');

    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'test:m90:goal:1' } },
      { dataset: { soundEvent: 'MATCH_WHISTLE_END', soundEventId: 'test:m90:whistle:end' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);

    assert(env.startedSources.length === 0, 'Zero reproduções no reload para eventos já ouvidos (deduplicação perfeita)');
    assert(audio.pendingQueue.size === 0, 'Fila pendente vazia no reload');
  }

  // 6. Proteção contra Rajadas Históricas (Eventos de minutos passados)
  console.log('\n6. Validando Proteção contra Rajadas de Eventos Históricos...');
  {
    const env = createMockEnvironment();
    // Simula página com 5 eventos históricos (15', 30', 45') e 1 evento contemporâneo (90')
    const elements = [
      { dataset: { soundEvent: 'MATCH_WHISTLE_START', soundEventId: 'h:15', soundHistorical: '1' } },
      { dataset: { soundEvent: 'NO_GOAL', soundEventId: 'h:30', soundHistorical: '1' } },
      { dataset: { soundEvent: 'MATCH_HALFTIME', soundEventId: 'h:45', soundHistorical: '1' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'h:55', soundHistorical: '1' } },
      { dataset: { soundEvent: 'PLAYER_SUBSTITUTION_IN', soundEventId: 'h:60', soundHistorical: '1' } },
      { dataset: { soundEvent: 'MATCH_WHISTLE_END', soundEventId: 'c:90' } } // contemporâneo
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    audio.cachedBuffers.set('match_whistle.ogg', Promise.resolve(env.dummyBuffers['match_whistle.ogg']));

    // Todos os 5 históricos devem ter sido marcados handled sem tocar
    assert(env.sessionData.get('rumo10_sound_played:h:15') === '1', 'Histórico 15 marcado handled');
    assert(env.sessionData.get('rumo10_sound_played:h:30') === '1', 'Histórico 30 marcado handled');
    assert(env.sessionData.get('rumo10_sound_played:h:45') === '1', 'Histórico 45 marcado handled');
    assert(env.sessionData.get('rumo10_sound_played:h:55') === '1', 'Histórico 55 marcado handled');
    assert(env.sessionData.get('rumo10_sound_played:h:60') === '1', 'Histórico 60 marcado handled');

    // Apenas o evento contemporâneo (MATCH_WHISTLE_END) toca
    const nonWhistleSources = env.startedSources.filter(s => s.buffer !== env.dummyBuffers['match_whistle.ogg']);
    assert(nonWhistleSources.length === 0, 'Zero reproduções de eventos históricos passados');
  }

  // 7. Política de Autoplay: Estado Suspenso e Unlock
  console.log('\n7. Validando Bloqueio de Autoplay e Destravamento (Unlock)...');
  {
    const env = createMockEnvironment();
    // Simular navegador bloqueando autoplay: AudioContext nasce suspenso
    env.mockWindow.__rumo10AudioCtx = new env.mockWindow.AudioContext();
    env.mockWindow.__rumo10AudioCtx.state = 'suspended';

    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'suspend:goal' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    audio.cachedBuffers.set('goal.ogg', Promise.resolve(env.dummyBuffers['goal.ogg']));

    assert(env.startedSources.length === 0, 'Zero som enquanto AudioContext suspenso por autoplay policy');
    assert(audio.pendingQueue.has('suspend:goal'), 'Som retido na fila pendingQueue aguardando gesto do usuário');

    // O usuário interage (clique, tecla) -> chama unlock()
    audio.unlock();
    await new Promise(r => setTimeout(r, 50));

    assert(env.mockWindow.__rumo10AudioCtx.state === 'running', 'AudioContext transita para running após unlock');
    assert(env.startedSources.length === 1, 'Som retido na fila é reproduzido imediatamente após unlock');
  }

  // 8. Controles de Mute e Volume
  console.log('\n8. Validando Controles de Mute e Volume...');
  {
    const env = createMockEnvironment();
    const audio = env.runScript();

    audio.setMuted(true);
    assert(audio.prefs.muted === true, 'Mute ativado com sucesso');
    assert(env.createdGains[0].gain.value === 0, 'Ganho master zerado no modo mudo');

    env.startedSources.length = 0;
    audio.enqueue('mute:test', 'GOAL_SCORED');
    assert(env.startedSources.length === 0, 'Nenhum som emitido enquanto estiver mudo');
    assert(env.sessionData.get('rumo10_sound_played:mute:test') === '1', 'Evento em mute marcado como handled para evitar blast posterior');

    audio.setMuted(false);
    audio.setVolume(0.5);
    assert(audio.prefs.volume === 0.5, 'Volume ajustado para 0.5');
    const expectedGain = Math.round(0.5 * 0.65 * 1000) / 1000;
    assert(Math.abs(env.createdGains[0].gain.value - expectedGain) < 0.01, `Ganho master reajustado proporcionalmente (${env.createdGains[0].gain.value})`);
  }

  // 9. Resiliência a Erro 404 e Falha de Decodificação
  console.log('\n9. Validando Resiliência a HTTP 404 de Áudio (sem bips nem crash)...');
  {
    const env = createMockEnvironment();
    // Elemento apontando para arquivo ausente (ex: no_goal.ogg com 404)
    const elements = [
      { dataset: { soundEvent: 'NO_GOAL', soundEventId: 'missing:nogoal' } },
      { dataset: { soundEvent: 'MATCH_WHISTLE_START', soundEventId: 'valid:whistle' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    audio.cachedBuffers.set('match_whistle.ogg', Promise.resolve(env.dummyBuffers['match_whistle.ogg']));

    await new Promise(r => setTimeout(r, 350));

    // O som com 404 deve ter sido capturado pelo catch sem lançar erro não tratado
    assert(env.sessionData.get('rumo10_sound_played:missing:nogoal') === '1', 'Arquivo 404 marcado handled para evitar loop infinito');
    // E o som válido subsequente na fila pôde tocar normalmente
    assert(env.startedSources.length === 1, 'Fila continuou normalmente e tocou o som válido seguinte');
    assert(env.startedSources[0].buffer === env.dummyBuffers['match_whistle.ogg'], 'Segundo som válido reproduzido');
  }

  console.log('\n=======================================================');
  console.log(`TOTAL P0-3 WEB AUDIO: ${passCount} verificações PASSOU, ${failCount} FALHOU`);
  console.log('=======================================================');

  if (failCount > 0) {
    process.exit(1);
  }
}

runTestSuite().catch(err => {
  console.error('Fatal error in Web Audio test suite:', err);
  process.exit(1);
});
