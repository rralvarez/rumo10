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
  const startedOscillators = [];
  const createdGains = [];
  const sessionData = new Map();
  const localData = new Map();
  const filesTo404 = new Set();

  class MockAudioParam {
    constructor(val = 1.0) {
      this.value = val;
    }
    setValueAtTime(val, time) {
      this.value = val;
    }
    linearRampToValueAtTime(val, time) {
      this.value = val;
    }
    exponentialRampToValueAtTime(val, time) {
      this.value = val;
    }
    setTargetAtTime(val, time, constant) {
      this.value = val;
    }
  }

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
    'no_goal.ogg': new MockAudioBuffer(2.95),
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

  class MockOscillatorNode {
    constructor(ctx) {
      this.ctx = ctx;
      this.type = 'sine';
      this.frequency = new MockAudioParam(440);
      this.connectedTo = null;
      this.startTime = null;
      this.stopTime = null;
      this.started = false;
      this.stopped = false;
      this.ended = false;
      this.onended = null;
      this.startCalls = 0;
      this.stopCalls = 0;
      this.failFirstStop = false;
      this.failPersistentStop = false;
      this.listeners = new Map();
      this.id = startedOscillators.length + 1;
    }
    connect(dest) {
      this.connectedTo = dest;
    }
    disconnect() {
      this.connectedTo = null;
    }
    addEventListener(event, listener, options) {
      if (!this.listeners.has(event)) {
        this.listeners.set(event, []);
      }
      this.listeners.get(event).push({ listener, once: !!(options && options.once) });
    }
    removeEventListener(event, listener) {
      if (this.listeners.has(event)) {
        const arr = this.listeners.get(event).filter(item => item.listener !== listener);
        this.listeners.set(event, arr);
      }
    }
    start(time) {
      this.startCalls++;
      this.started = true;
      this.startTime = time;
      startedOscillators.push(this);
    }
    stop(time) {
      this.stopCalls++;
      if (this.failPersistentStop) {
        throw new Error('Falha persistente simulada em stop()');
      }
      if (this.failFirstStop && this.stopCalls === 1) {
        throw new Error('Falha simulada no primeiro stop() programado');
      }
      this.stopped = true;
      this.stopTime = time;
    }
    triggerEnd() {
      if (this.ended) return false;
      // Se o contexto estiver suspenso, o relógio de áudio está parado: não emite ended!
      if (this.ctx && this.ctx.state === 'suspended') {
        return false;
      }
      this.ended = true;
      if (typeof this.onended === 'function') {
        try { this.onended({ type: 'ended', target: this }); } catch (_) {}
      }
      if (this.listeners.has('ended')) {
        const list = [...this.listeners.get('ended')];
        for (const item of list) {
          try { item.listener({ type: 'ended', target: this }); } catch (_) {}
          if (item.once) {
            this.removeEventListener('ended', item.listener);
          }
        }
      }
      return true;
    }
  }

  class MockGainNode {
    constructor(ctx) {
      this.ctx = ctx;
      this.gain = new MockAudioParam(1.0);
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
      this.resumeCalls = 0;
    }
    createBufferSource() {
      return new MockAudioBufferSourceNode(this);
    }
    createOscillator() {
      return new MockOscillatorNode(this);
    }
    createGain() {
      return new MockGainNode(this);
    }
    decodeAudioData(bytes) {
      const buf = dummyBuffers[lastFetchedFile] || new MockAudioBuffer(2.5);
      return Promise.resolve(buf);
    }
    async resume() {
      this.resumeCalls++;
      this.state = 'running';
      return Promise.resolve();
    }
    async suspend() {
      this.state = 'suspended';
      return Promise.resolve();
    }
  }

  const mockDomElements = [];

  const mockWindow = {
    console: console,
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
      if (dummyBuffers[filename] && !filesTo404.has(filename)) {
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
    setTimeout: (fn, ms) => setTimeout(fn, ms),
    clearTimeout: (id) => clearTimeout(id),
    performance: { now: () => performance.now() }
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
    startedOscillators,
    createdGains,
    sessionData,
    localData,
    mockDomElements,
    dummyBuffers,
    filesTo404
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
    // Simula resposta HTTP 404 no endpoint de áudio
    env.filesTo404.add('substitution_out.ogg');
    const elements = [
      { dataset: { soundEvent: 'PLAYER_SUBSTITUTION_OUT', soundEventId: 'missing:404:test' } },
      { dataset: { soundEvent: 'MATCH_WHISTLE_START', soundEventId: 'valid:whistle' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    audio.cachedBuffers.set('match_whistle.ogg', Promise.resolve(env.dummyBuffers['match_whistle.ogg']));

    await new Promise(r => setTimeout(r, 350));

    // O som com 404 deve ter sido capturado pelo catch sem lançar erro não tratado
    assert(env.sessionData.get('rumo10_sound_played:missing:404:test') === '1', 'Arquivo 404 marcado handled para evitar loop infinito');
    // E o som válido subsequente na fila pôde tocar normalmente
    assert(env.startedSources.length === 1, 'Fila continuou normalmente e tocou o som válido seguinte');
    assert(env.startedSources[0].buffer === env.dummyBuffers['match_whistle.ogg'], 'Segundo som válido reproduzido');
  }

  // 10. Regressão P0-4: Cartão amarelo emitido no lance da última ação após avanço de minuto
  console.log('\n10. Validando P0-4: Cartão de última ação após avanço de minuto toca sem ser descartado...');
  {
    const env = createMockEnvironment();
    // Simula markup gerado por Presentation::matchSounds onde o minuto avançou para 35, mas o cartão é recente (sem data-sound-historical="1")
    const elements = [
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'act:card:yellow:15' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 1, 'P0-4: CARD_YELLOW é reproduzido imediatamente pelo motor');
    assert(env.startedSources[0].buffer === env.dummyBuffers['yellow_card.ogg'], 'P0-4: Buffer utilizado é yellow_card.ogg');
    
    // Conclui reprodução
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(env.sessionData.get('rumo10_sound_played:act:card:yellow:15') === '1', 'P0-4: Cartão registrado como tocado no sessionStorage');

    // Simula reload (F5): não deve tocar novamente
    env.startedSources.length = 0;
    env.runScript(elements);
    assert(env.startedSources.length === 0, 'P0-4: Recarga não reexecuta o som de cartão já ouvido');
  }

  // 11. Sons de Substituição (PLAYER_SUBSTITUTION_IN e OUT)
  console.log('\n11. Validando Sons de Substituição (Entrada e Saída)...');
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'PLAYER_SUBSTITUTION_IN', soundEventId: 'sub:in:60' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 1, 'PLAYER_SUBSTITUTION_IN reproduzido pelo motor');
    assert(env.startedSources[0].buffer === env.dummyBuffers['substitution_in.ogg'], 'Buffer utilizado é substitution_in.ogg');

    // Teste de saída
    const envOut = createMockEnvironment();
    const elementsOut = [
      { dataset: { soundEvent: 'PLAYER_SUBSTITUTION_OUT', soundEventId: 'sub:out:70' } }
    ];

    envOut.startedSources.length = 0;
    const audioOut = envOut.runScript(elementsOut);
    await new Promise(r => setTimeout(r, 50));

    assert(envOut.startedSources.length === 1, 'PLAYER_SUBSTITUTION_OUT reproduzido pelo motor');
    assert(envOut.startedSources[0].buffer === envOut.dummyBuffers['substitution_out.ogg'], 'Buffer utilizado é substitution_out.ogg');
  }

  // 12. Validação da Fila de 4 Eventos Contemporâneos Legítimos (P1-C R2: Gol + Pênalti + Substituição + Apito Final)
  console.log('\n12. Validando Fila Contemporânea de 4 Eventos Legítimos (Gol + Pênalti + Substituição + Apito Final)...');
  {
    const env = createMockEnvironment();
    // Cenário real com 4 eventos legítimos emitidos no mesmo segmento final:
    // 1. Gol (100)
    // 2. Pênalti concedido (82 - sintetizado)
    // 3. Substituição por desgaste (70)
    // 4. Apito final (50 - 3 pulsos)
    const elements = [
      { dataset: { soundEvent: 'MATCH_WHISTLE_END', soundEventId: 'act:90:whistle' } },
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'act:90:goal' } },
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'act:90:penalty' } },
      { dataset: { soundEvent: 'PLAYER_SUBSTITUTION_IN', soundEventId: 'act:90:sub' } },
    ];

    env.startedSources.length = 0;
    env.startedOscillators.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    // P1-C R2 CRUCIAL: O 4º evento (MATCH_WHISTLE_END) NÃO pode ser descartado arbitrariamente!
    // Ele DEVE permanecer na fila pendente e NÃO pode receber markHandled precocemente.
    assert(audio.pendingQueue.has('act:90:whistle'), 'P1-C R2: O 4º evento (apito final) foi PRESERVADO na fila pendente (sem descarte)');
    assert(env.sessionData.get('rumo10_sound_played:act:90:whistle') !== '1', 'P1-C R2: O 4º evento NÃO recebe markHandled antes do efetivo agendamento');
    assert(audio.pendingQueue.has('act:90:penalty'), 'P1-C R2: Pênalti concedido preservado na fila');
    assert(audio.pendingQueue.has('act:90:sub'), 'P1-C R2: Substituição preservada na fila');

    // 1º evento reproduzido: GOAL_SCORED (prioridade 100)
    assert(env.startedSources.length === 1, 'P1-C R2: 1º evento da fila inicia reprodução imediatamente');
    assert(env.startedSources[0].buffer === env.dummyBuffers['goal.ogg'], 'P1-C R2: 1º a tocar: GOAL_SCORED (prioridade 100)');
    assert(env.sessionData.get('rumo10_sound_played:act:90:goal') === '1', 'P1-C R2: Gol registrado como tocado');

    // Conclui o gol -> transição de 250ms -> 2º evento: PENALTY_AWARDED (prioridade 82)
    const goalSource = env.startedSources[0].source;
    goalSource.triggerEnd();
    await new Promise(r => setTimeout(r, 350));

    assert(env.startedOscillators.length === 1, 'P1-C R2: 2º a tocar: PENALTY_AWARDED sintetizado (prioridade 82)');
    assert(env.sessionData.get('rumo10_sound_played:act:90:penalty') === '1', 'P1-C R2: Pênalti concedido registrado como tocado');
    assert(audio.pendingQueue.has('act:90:whistle'), 'P1-C R2: O 4º evento permanece na fila após o 2º som');

    // Conclui o oscilador do apito sintetizado de pênalti via evento ended real
    env.startedOscillators[0].triggerEnd();

    // Aguarda término do espaçamento regulamentar de 250ms -> 3º evento: PLAYER_SUBSTITUTION_IN (prioridade 70)
    await new Promise(r => setTimeout(r, 350));

    assert(env.startedSources.length === 2, 'P1-C R2: 3º a tocar: PLAYER_SUBSTITUTION_IN inicia (prioridade 70)');
    assert(env.startedSources[1].buffer === env.dummyBuffers['substitution_in.ogg'], 'P1-C R2: Buffer é substitution_in.ogg');
    assert(env.sessionData.get('rumo10_sound_played:act:90:sub') === '1', 'P1-C R2: Substituição registrada como tocada');
    assert(audio.pendingQueue.has('act:90:whistle'), 'P1-C R2: O 4º evento permanece na fila após o 3º som');

    // Conclui a substituição -> transição de 250ms -> 4º evento: MATCH_WHISTLE_END (prioridade 50, 3 apitos)
    const subSource = env.startedSources[1].source;
    subSource.triggerEnd();
    await new Promise(r => setTimeout(r, 350));

    assert(env.startedSources.length === 5, 'P1-C R2: 4º a tocar: MATCH_WHISTLE_END reproduz seus 3 pulsos sobrepostos (total de 5 sources)');
    assert(env.startedSources[2].buffer === env.dummyBuffers['match_whistle.ogg'], 'P1-C R2: MATCH_WHISTLE_END pulso 1 agendado');
    assert(env.startedSources[3].buffer === env.dummyBuffers['match_whistle.ogg'], 'P1-C R2: MATCH_WHISTLE_END pulso 2 agendado');
    assert(env.startedSources[4].buffer === env.dummyBuffers['match_whistle.ogg'], 'P1-C R2: MATCH_WHISTLE_END pulso 3 agendado');
    assert(env.sessionData.get('rumo10_sound_played:act:90:whistle') === '1', 'P1-C R2: O 4º evento é registrado como tocado no agendamento');

    // Conclui os apitos finais
    const whistleSources = env.startedSources.filter(s => s.buffer === env.dummyBuffers['match_whistle.ogg']);
    whistleSources.forEach(s => s.source.triggerEnd());
    await new Promise(r => setTimeout(r, 50));

    assert(audio.pendingQueue.size === 0, 'P1-C R2: Fila de 4 eventos contemporâneos totalmente esvaziada');

    // F5 após os 4 eventos: NENHUM som é re-executado
    env.startedSources.length = 0;
    env.startedOscillators.length = 0;
    env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));
    assert(env.startedSources.length === 0, 'P1-C R2: Deduplicação no F5 após 4 eventos mantém silêncio absoluto');
    assert(env.startedOscillators.length === 0, 'P1-C R2: Zero osciladores sintetizados no reload F5');
  }

  // 13. Validação de Cartão Vermelho Direto e Segundo Amarelo (CARD_RED)
  console.log('\n13. Validando Expulsão e Segundo Amarelo (CARD_RED)...');
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'CARD_RED', soundEventId: 'card:red:75' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 1, 'CARD_RED reproduzido pelo motor Web Audio');
    assert(env.startedSources[0].buffer === env.dummyBuffers['red_card.ogg'], 'Buffer utilizado é red_card.ogg');

    // Conclui o som
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(env.sessionData.get('rumo10_sound_played:card:red:75') === '1', 'CARD_RED registrado no sessionStorage');

    // F5 não repete
    env.startedSources.length = 0;
    env.runScript(elements);
    assert(env.startedSources.length === 0, 'Deduplicação no F5 para CARD_RED confirmada');
  }

  // 14. Validação de NO_GOAL com no_goal.ogg (chute para fora / pênalti desperdiçado)
  console.log('\n14. Validando Reprodução de NO_GOAL com no_goal.ogg...');
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'NO_GOAL', soundEventId: 'act:nogoal:pen:30' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 1, 'NO_GOAL reproduzido pelo motor Web Audio');
    assert(env.startedSources[0].buffer === env.dummyBuffers['no_goal.ogg'], 'Buffer utilizado é no_goal.ogg (2.95s)');

    // Conclui o som
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(env.sessionData.get('rumo10_sound_played:act:nogoal:pen:30') === '1', 'NO_GOAL registrado no sessionStorage');

    // F5 não repete
    env.startedSources.length = 0;
    env.runScript(elements);
    assert(env.startedSources.length === 0, 'Deduplicação no F5 para NO_GOAL confirmada');
  }

  // 15. Regressão P1-A: Deduplicação Imediata no F5 DURANTE a reprodução (antes de onended)
  console.log('\n15. Validando Regressão P1-A: Deduplicação no F5 DURANTE a reprodução ativa...');
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'goal:p1a:midflight' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 1, 'P1-A: GOAL_SCORED iniciou reprodução');
    // P1-A CRUCIAL: O evento DEVE ser marcado em sessionStorage e handled no momento do source.start(),
    // ANTES de source.onended ser disparado!
    assert(env.sessionData.get('rumo10_sound_played:goal:p1a:midflight') === '1', 'P1-A: sessionStorage registrado IMEDIATAMENTE ao agendar source.start() (t=0)');
    assert(audio.handled.has('goal:p1a:midflight'), 'P1-A: this.handled contém o evento imediatamente');

    // Simula recarga de página (F5) aos 159ms de reprodução (quando o som ainda está tocando)
    const envReload = createMockEnvironment();
    // Compartilha o sessionStorage da sessão da aba
    env.sessionData.forEach((v, k) => envReload.sessionData.set(k, v));
    envReload.startedSources.length = 0;
    const audioReload = envReload.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(envReload.startedSources.length === 0, 'P1-A: F5 durante reprodução em andamento mantém silêncio absoluto (zero reexecuções)');
    assert(audioReload.pendingQueue.size === 0, 'P1-A: Fila vazia na nova página');

    // Conclui o som original para resolução limpa
    env.startedSources[0].source.triggerEnd();
  }

  // 16. Regressão P1-B: Preservação de evento em pendingQueue com AudioContext suspenso
  console.log('\n16. Validando Regressão P1-B: Preservação em pendingQueue com AudioContext suspenso...');
  {
    const env = createMockEnvironment();
    // Simula AudioContext suspenso por autoplay policy do navegador
    env.mockWindow.__rumo10AudioCtx = new env.mockWindow.AudioContext();
    env.mockWindow.__rumo10AudioCtx.state = 'suspended';

    const elements = [
      { dataset: { soundEvent: 'NO_GOAL', soundEventId: 'act:p1b:suspended' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    audio.cachedBuffers.set('no_goal.ogg', Promise.resolve(env.dummyBuffers['no_goal.ogg']));
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 0, 'P1-B: Nenhum som toca enquanto AudioContext estiver suspenso');
    // P1-B CRUCIAL: NÃO marcar handled enquanto suspenso, pois o som ainda não foi ouvido pelo usuário!
    assert(env.sessionData.get('rumo10_sound_played:act:p1b:suspended') !== '1', 'P1-B: Evento NÃO é marcado como handled no sessionStorage enquanto suspenso');
    assert(!audio.handled.has('act:p1b:suspended'), 'P1-B: this.handled NÃO contém o ID enquanto suspenso');
    assert(!audio.inFlight.has('act:p1b:suspended'), 'P1-B: this.inFlight limpo após tentativa com suspenso');
    assert(audio.pendingQueue.has('act:p1b:suspended'), 'P1-B: Evento preservado na pendingQueue para reprodução pós-unlock');

    // Usuário interage com a página -> AudioEngine.unlock()
    audio.unlock();
    await new Promise(r => setTimeout(r, 100));

    assert(env.mockWindow.__rumo10AudioCtx.state === 'running', 'P1-B: AudioContext transitou para running após unlock');
    assert(env.startedSources.length === 1, 'P1-B: Evento preservado entra em reprodução imediatamente após unlock');
    assert(env.startedSources[0].buffer === env.dummyBuffers['no_goal.ogg'], 'P1-B: Buffer utilizado é no_goal.ogg');
    assert(env.sessionData.get('rumo10_sound_played:act:p1b:suspended') === '1', 'P1-B: Evento devidamente marcado como handled após início do playback');
  }

  // 17. Regressão P1-C: Compatibilidade com Efeitos Sintetizados Legados (BALL_HIT_POST e PENALTY_AWARDED)
  console.log('\n17. Validando Regressão P1-C: Compatibilidade com Efeitos Sintetizados (BALL_HIT_POST e PENALTY_AWARDED)...');
  {
    // 17.1 BALL_HIT_POST isolado
    const envPost = createMockEnvironment();
    const elementsPost = [
      { dataset: { soundEvent: 'BALL_HIT_POST', soundEventId: 'synth:post:1' } }
    ];

    envPost.startedOscillators.length = 0;
    const audioPost = envPost.runScript(elementsPost);
    await new Promise(r => setTimeout(r, 50));

    assert(envPost.startedOscillators.length === 2, 'P1-C: BALL_HIT_POST sintetiza 2 osciladores (triângulo 160Hz + senoide 80Hz)');
    assert(audioPost.wasHandled('synth:post:1'), 'P1-C: BALL_HIT_POST marcado como handled imediatamente');
    assert(envPost.sessionData.get('rumo10_sound_played:synth:post:1') === '1', 'P1-C: BALL_HIT_POST registrado no sessionStorage');

    // F5 para BALL_HIT_POST
    envPost.startedOscillators.length = 0;
    envPost.runScript(elementsPost);
    assert(envPost.startedOscillators.length === 0, 'P1-C: F5 para BALL_HIT_POST mantém silêncio');

    // 17.2 PENALTY_AWARDED isolado
    const envPen = createMockEnvironment();
    const elementsPen = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'synth:pen:1' } }
    ];

    envPen.startedOscillators.length = 0;
    const audioPen = envPen.runScript(elementsPen);
    await new Promise(r => setTimeout(r, 50));

    assert(envPen.startedOscillators.length === 1, 'P1-C: PENALTY_AWARDED sintetiza 1 apito enérgico longo (2500Hz)');
    assert(audioPen.wasHandled('synth:pen:1'), 'P1-C: PENALTY_AWARDED marcado como handled imediatamente');
    assert(envPen.sessionData.get('rumo10_sound_played:synth:pen:1') === '1', 'P1-C: PENALTY_AWARDED registrado no sessionStorage');

    // 17.3 Prioridade na fila mista com OGG e Efeitos Sintetizados
    // BALL_HIT_POST (prioridade 88) deve tocar antes de NO_GOAL (85) e CARD_YELLOW (75)
    const envMixed = createMockEnvironment();
    const elementsMixed = [
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'mix:card:75' } },
      { dataset: { soundEvent: 'BALL_HIT_POST', soundEventId: 'mix:post:88' } },
      { dataset: { soundEvent: 'NO_GOAL', soundEventId: 'mix:nogoal:85' } }
    ];

    envMixed.startedSources.length = 0;
    envMixed.startedOscillators.length = 0;
    const audioMixed = envMixed.runScript(elementsMixed);
    await new Promise(r => setTimeout(r, 50));

    // O primeiro a tocar DEVE ser BALL_HIT_POST (maior prioridade 88)
    assert(envMixed.startedOscillators.length === 2, 'P1-C: BALL_HIT_POST (prioridade 88) toca PRIMEIRO na fila mista');
    assert(audioMixed.pendingQueue.has('mix:nogoal:85'), 'P1-C: NO_GOAL (prioridade 85) aguarda na fila');
    assert(audioMixed.pendingQueue.has('mix:card:75'), 'P1-C: CARD_YELLOW (prioridade 75) aguarda na fila');

    // Conclui os dois osciladores de BALL_HIT_POST via ended real
    envMixed.startedOscillators[0].triggerEnd();
    envMixed.startedOscillators[1].triggerEnd();

    // Aguardar término do espaçamento regulamentar de 250ms
    await new Promise(r => setTimeout(r, 350));

    // O segundo a tocar DEVE ser NO_GOAL (prioridade 85)
    assert(envMixed.startedSources.length === 1, 'P1-C: NO_GOAL inicia em segundo lugar na fila mista');
    assert(envMixed.startedSources[0].buffer === envMixed.dummyBuffers['no_goal.ogg'], 'P1-C: Buffer é no_goal.ogg');

    // Conclui NO_GOAL
    envMixed.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 350));

    // O terceiro a tocar DEVE ser CARD_YELLOW (prioridade 75)
    assert(envMixed.startedSources.length === 2, 'P1-C: CARD_YELLOW inicia em terceiro lugar na fila mista');
    assert(envMixed.startedSources[1].buffer === envMixed.dummyBuffers['yellow_card.ogg'], 'P1-C: Buffer é yellow_card.ogg');

    // Conclui CARD_YELLOW
    envMixed.startedSources[1].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));

    assert(audioMixed.pendingQueue.size === 0, 'P1-C: Fila mista totalmente processada');
    assert(envMixed.sessionData.get('rumo10_sound_played:mix:post:88') === '1', 'P1-C: BALL_HIT_POST registrado no sessionStorage');
    assert(envMixed.sessionData.get('rumo10_sound_played:mix:nogoal:85') === '1', 'P1-C: NO_GOAL registrado no sessionStorage');
    assert(envMixed.sessionData.get('rumo10_sound_played:mix:card:75') === '1', 'P1-C: CARD_YELLOW registrado no sessionStorage');
  }

  // 18. Validando Pendência Residual de Mute (P2 Residual: Mute ativado e desativado durante download)
  console.log('\n18. Validando Pendência Residual de Mute (Mute ativado e desativado durante download assíncrono)...');
  {
    const env = createMockEnvironment();
    let resolveFetch = null;
    // Intercepta fetch para simular download assíncrono em andamento
    const originalFetch = env.mockWindow.fetch;
    env.mockWindow.fetch = (url) => {
      if (url.includes('goal.ogg')) {
        return new Promise(resolve => {
          resolveFetch = () => resolve(originalFetch(url));
        });
      }
      return originalFetch(url);
    };

    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'mute:during:download' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    // O áudio está em download (inFlight)
    assert(audio.inFlight.has('mute:during:download'), 'P2 Residual: Evento está inFlight aguardando download do buffer');
    assert(env.startedSources.length === 0, 'P2 Residual: Nenhum source disparado antes do download completar');

    // Usuário ativa mute enquanto o download está ocorrendo
    audio.setMuted(true);
    assert(audio.wasHandled('mute:during:download'), 'P2 Residual: Evento inFlight é marcado handled ao ativar mute');
    assert(env.sessionData.get('rumo10_sound_played:mute:during:download') === '1', 'P2 Residual: Registrado no sessionStorage');

    // Usuário desativa mute ANTES do download completar
    audio.setMuted(false);
    assert(!audio.prefs.muted, 'P2 Residual: Mute desativado pelo usuário antes do download completar');

    // O download finalmente termina
    resolveFetch();
    await new Promise(r => setTimeout(r, 100));

    // COMPROVAÇÃO: O áudio cancelado NÃO toca! Zero sons disparados!
    assert(env.startedSources.length === 0, 'P2 Residual: Áudio cancelado durante download NÃO toca ao resolver fetch (zero vazamentos)');
  }

  // 19. Validando R3: Blindagem Temporal do Espaçamento de 250ms contra Cliques na Pausa (Codex R3)
  console.log('\n19. Validando R3: Blindagem Temporal do Espaçamento de 250ms contra Cliques na Pausa (Codex R3)...');

  // 19.A: Espaçamento normal >= 250ms entre eventos sucessivos
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'gap:goal' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'gap:card' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 1, '19.A: Primeiro evento (Gol) inicia imediatamente');
    const goalSource = env.startedSources[0].source;

    const tEndGoal = env.mockWindow.performance.now();
    goalSource.triggerEnd();

    // Aguarda até o segundo evento iniciar
    while (env.startedSources.length < 2) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStartCard = env.mockWindow.performance.now();
    const gap = tStartCard - tEndGoal;

    assert(gap >= 250, `19.A: Espaçamento regulamentar aferido: ${gap.toFixed(1)}ms >= 250ms`);
    assert(env.startedSources[1].buffer === env.dummyBuffers['yellow_card.ogg'], '19.A: Segundo evento executado com sucesso');
  }

  // 19.B: Clique / unlock() aos 30ms da pausa NÃO inicia o som antes de 250ms
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'click:goal' } },
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'click:penalty' } }
    ];

    env.startedSources.length = 0;
    env.startedOscillators.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 1, '19.B: 1º evento (Gol) inicia imediatamente');
    const goalSource = env.startedSources[0].source;

    // Conclui o gol
    const tEndGoal = env.mockWindow.performance.now();
    goalSource.triggerEnd();

    // Aguarda 30ms após a conclusão (dentro da pausa contratual de 250ms)
    await new Promise(r => setTimeout(r, 30));

    // SIMULAÇÃO DO DEFEITO DO CODEX R3: Usuário clica na tela aos 30ms da pausa!
    audio.unlock();

    // Imediatamente e aos 50ms, 100ms e 200ms da pausa: o 2º som NÃO pode ter iniciado!
    assert(env.startedOscillators.length === 0, '19.B (Codex R3): Aos 30ms após clique, 2º som NÃO disparou precocemente');
    await new Promise(r => setTimeout(r, 50)); // t ≈ 80ms
    assert(env.startedOscillators.length === 0, '19.B: Aos 80ms, 2º som permanece aguardando a janela de 250ms');
    await new Promise(r => setTimeout(r, 100)); // t ≈ 180ms
    assert(env.startedOscillators.length === 0, '19.B: Aos 180ms, 2º som permanece aguardando a janela de 250ms');

    // Aguarda até o segundo evento iniciar após completar os 250ms
    while (env.startedOscillators.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStartPenalty = env.mockWindow.performance.now();
    const measuredGap = tStartPenalty - tEndGoal;

    assert(measuredGap >= 250, `19.B (Codex R3): Gap efetivo protegido contra clique: ${measuredGap.toFixed(1)}ms >= 250ms`);
    assert(env.startedOscillators.length === 1, '19.B: PENALTY_AWARDED tocou pontualmente após 250ms');

    // Conclui o oscilador de pênalti
    env.startedOscillators[0].triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(audio.pendingQueue.size === 0, '19.B: Fila esvaziada');
  }

  // 19.C: Cliques múltiplos e rápidos durante a pausa não duplicam, não criam timers concorrentes e não perdem eventos
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'multi:goal' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'multi:card' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedSources.length === 1, '19.C: Primeiro evento inicia');
    const goalSource = env.startedSources[0].source;

    const tEnd = env.mockWindow.performance.now();
    goalSource.triggerEnd();

    // Rajada de interações do usuário durante a pausa
    await new Promise(r => setTimeout(r, 20));
    audio.unlock();
    await new Promise(r => setTimeout(r, 30));
    audio.unlock();
    await new Promise(r => setTimeout(r, 40));
    audio.unlock();
    await new Promise(r => setTimeout(r, 50));
    audio.unlock();
    await new Promise(r => setTimeout(r, 40)); // t ≈ 180ms
    audio.unlock();

    // Até este momento (180ms), zero execuções antecipadas
    assert(env.startedSources.length === 1, '19.C: Cliques repetidos na pausa não adiantam o disparo');

    // Aguarda término da pausa
    while (env.startedSources.length < 2) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStart = env.mockWindow.performance.now();
    const gapMulti = tStart - tEnd;

    assert(gapMulti >= 250, `19.C: Gap protegido contra múltiplos cliques: ${gapMulti.toFixed(1)}ms >= 250ms`);
    assert(env.startedSources.length === 2, '19.C: Exatamente 1 disparo para o segundo evento (sem duplicações)');

    // Conclui o segundo evento
    env.startedSources[1].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));

    assert(audio.pendingQueue.size === 0, '19.C: Fila esvaziada sem perda de eventos');
  }

  // 19.D: Suspensão do AudioContext durante a fila e retomada mantendo integridade e espaçamento
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'susp:goal' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'susp:card' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    const goalSource = env.startedSources[0].source;
    goalSource.triggerEnd();

    // Contexto é suspenso pelo navegador durante a pausa (ex: troca de aba / background)
    audio.ctx.state = 'suspended';
    await new Promise(r => setTimeout(r, 300)); // Tempo normal transcorreu, mas contexto está suspenso

    // Nenhum som novo deve ter tocado enquanto suspenso
    assert(env.startedSources.length === 1, '19.D: Zero sons enquanto AudioContext estiver suspenso');
    assert(audio.pendingQueue.has('susp:card'), '19.D: Segundo som retido intacto na fila pendente');

    // Usuário retorna e clica (unlock)
    audio.unlock();
    await new Promise(r => setTimeout(r, 80));

    // O contexto voltou para running e o som retido foi tocado imediatamente (já que os 250ms já haviam passado)
    assert(audio.ctx.state === 'running', '19.D: Contexto retomado para running');
    assert(env.startedSources.length === 2, '19.D: Som pendente reproduzido após unlock');
    assert(env.startedSources[1].buffer === env.dummyBuffers['yellow_card.ogg'], '19.D: Buffer yellow_card.ogg executado');

    // Conclui o cartão amarelo
    env.startedSources[1].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));

    assert(audio.pendingQueue.size === 0, '19.D: Fila limpa com integridade garantida');
  }

  // 19.E: Falha de asset (404 / decode) e mute mantêm consistência temporal sem travamento
  {
    const env = createMockEnvironment();
    env.filesTo404.add('red_card.ogg');

    const elements = [
      { dataset: { soundEvent: 'CARD_RED', soundEventId: 'err:card:red' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'valid:card:yellow' } }
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    // Aguarda o tratamento do 404 (assíncrono)
    await new Promise(r => setTimeout(r, 50));

    // O erro terminal 404 foi tratado graciosamente, removeu o item e agendou o próximo com espaçamento
    assert(audio.wasHandled('err:card:red'), '19.E: Item com erro marcado como handled sem travar');

    // Aguarda o espaçamento regulamentar (250ms) para o próximo som (Cartão Amarelo)
    await new Promise(r => setTimeout(r, 350));

    assert(env.startedSources.length === 1, '19.E: Próximo som válido tocou normalmente após o erro');
    assert(env.startedSources[0].buffer === env.dummyBuffers['yellow_card.ogg'], '19.E: Buffer yellow_card.ogg tocou');

    // Conclui o som válido
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(audio.pendingQueue.size === 0, '19.E: Fila limpa após recuperação de erro');
  }

  // 20. Validando R4: Ciclo de Vida Efetivo dos Sintetizadores via Evento Ended (Codex R4 - N1 a N8)
  console.log('\n20. Validando R4: Ciclo de Vida dos Sintetizadores via Evento Ended (Codex R4 - N1 a N8)...');

  // N1. Pênalti sintetizado sem suspensão
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'n1:pen' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'n1:card' } }
    ];
    env.startedOscillators.length = 0;
    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedOscillators.length === 1, 'N1: Exatamente 1 oscilador para PENALTY_AWARDED');
    assert(audio.wasHandled('n1:pen'), 'N1: ID do pênalti marcado como handled imediatamente após agendamento');
    assert(audio.playing === true, 'N1: playing permanece true enquanto oscilador não conclui');
    assert(env.startedSources.length === 0, 'N1: Próximo evento (Cartão) NÃO inicia antes do término do oscilador');

    const penOsc = env.startedOscillators[0];
    const tEnd = env.mockWindow.performance.now();
    penOsc.triggerEnd();

    // Aguarda o próximo som iniciar
    while (env.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStartCard = env.mockWindow.performance.now();
    const gap = tStartCard - tEnd;

    assert(gap >= 250, `N1: Espaçamento regulamentar após ended: ${gap.toFixed(1)}ms >= 250ms`);
    assert(env.startedSources[0].buffer === env.dummyBuffers['yellow_card.ogg'], 'N1: Próximo som tocou yellow_card.ogg');
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(audio.pendingQueue.size === 0, 'N1: Fila limpa');
  }

  // N2. Pênalti sintetizado suspenso
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'n2:pen' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'n2:card' } }
    ];
    env.startedOscillators.length = 0;
    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedOscillators.length === 1, 'N2: Oscilador iniciado');
    const penOsc = env.startedOscillators[0];

    // Suspende o contexto enquanto o oscilador está ativo
    audio.ctx.state = 'suspended';

    // Tenta triggerEnd durante suspensão -> não pode emitir ended!
    const triggerSuspendedResult = penOsc.triggerEnd();
    assert(triggerSuspendedResult === false, 'N2: Nenhum ended emitido enquanto AudioContext estiver suspenso');
    assert(!penOsc.ended, 'N2: Oscilador permanece não-encerrado durante suspensão');

    // Avança o relógio JavaScript por mais de 1 segundo
    await new Promise(r => setTimeout(r, 1100));

    assert(audio.playing === true, 'N2: playing continua true após 1.1s de relógio JS em suspensão');
    assert(audio.pendingQueue.has('n2:card'), 'N2: Próximo evento permanece pendente na fila');
    assert(env.startedSources.length === 0, 'N2: Zero sons disparados durante a suspensão');

    // Retoma o contexto
    await audio.ctx.resume();
    assert(audio.ctx.state === 'running', 'N2: Contexto retomado para running');

    // Agora sim conclui o oscilador
    const tEnd = env.mockWindow.performance.now();
    const triggerResumedResult = penOsc.triggerEnd();
    assert(triggerResumedResult === true, 'N2: ended disparado após retomada');

    // Aguarda o próximo som iniciar
    while (env.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStartCard = env.mockWindow.performance.now();
    const gap = tStartCard - tEnd;

    assert(gap >= 250, `N2: Espaçamento de 250ms respeitado após término real: ${gap.toFixed(1)}ms >= 250ms`);
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
  }

  // N3. Bola na trave com dois osciladores
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'BALL_HIT_POST', soundEventId: 'n3:post' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'n3:card' } }
    ];
    env.startedOscillators.length = 0;
    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedOscillators.length === 2, 'N3: Exatamente 2 osciladores criados para BALL_HIT_POST');
    const osc1 = env.startedOscillators[0];
    const osc2 = env.startedOscillators[1];

    // Conclui APENAS o primeiro oscilador
    osc1.triggerEnd();
    await new Promise(r => setTimeout(r, 100));

    // Fila DEVE continuar bloqueada
    assert(audio.playing === true, 'N3: Fila permanece bloqueada (playing=true) após término apenas do 1º oscilador');
    assert(env.startedSources.length === 0, 'N3: Próximo som NÃO inicia após apenas 1º oscilador');
    assert(audio.pendingQueue.has('n3:card'), 'N3: Próximo som permanece na pendingQueue');

    // Conclui o segundo oscilador
    const tEnd = env.mockWindow.performance.now();
    osc2.triggerEnd();

    // Aguarda próximo som
    while (env.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStart = env.mockWindow.performance.now();
    const gap = tStart - tEnd;

    assert(gap >= 250, `N3: Barreira de 250ms começa rigorosamente após o 2º oscilador: ${gap.toFixed(1)}ms >= 250ms`);
    assert(env.startedSources.length === 1, 'N3: Próximo som inicia');
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
  }

  // N4. Eventos ended duplicados
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'n4:pen' } }
    ];
    env.startedOscillators.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    const osc = env.startedOscillators[0];
    const firstEnd = osc.triggerEnd();
    assert(firstEnd === true, 'N4: 1º disparo de ended aceito');

    // Tenta entregar ended pela 2ª vez no mesmo oscilador
    const secondEnd = osc.triggerEnd();
    assert(secondEnd === false, 'N4: 2º disparo de ended no mesmo oscilador é rejeitado pelo mock');

    // Também simula chamada direta a onended se houver
    if (typeof osc.onended === 'function') {
      try { osc.onended(); } catch (_) {}
    }
    await new Promise(r => setTimeout(r, 50));

    assert(audio.wasHandled('n4:pen'), 'N4: Evento marcado handled normalmente');
    assert(audio.pendingQueue.size === 0, 'N4: Fila esvaziada');
  }

  // N5. Falha parcial de agendamento em BALL_HIT_POST
  {
    const env = createMockEnvironment();
    let oscCount = 0;
    const origCreateOsc = env.mockWindow.AudioContext.prototype.createOscillator;
    env.mockWindow.AudioContext.prototype.createOscillator = function() {
      oscCount++;
      if (oscCount === 2) {
        throw new Error('Falha simulada na criação do 2º oscilador');
      }
      return origCreateOsc.call(this);
    };

    const elements = [
      { dataset: { soundEvent: 'BALL_HIT_POST', soundEventId: 'n5:post' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'n5:card' } }
    ];
    env.startedOscillators.length = 0;
    env.startedSources.length = 0;
    const audio = env.runScript(elements);

    // Restaura createOscillator
    env.mockWindow.AudioContext.prototype.createOscillator = origCreateOsc;

    await new Promise(r => setTimeout(r, 50));

    // O 1º oscilador foi interrompido e desconectado
    assert(audio.wasHandled('n5:post'), 'N5: Evento com falha terminal foi marcado handled para evitar loop');
    assert(!audio.pendingQueue.has('n5:post'), 'N5: Evento com falha removido da fila pendente');

    // Aguarda o próximo evento válido tocar após o espaçamento
    while (env.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    assert(env.startedSources.length === 1, 'N5: Próximo evento (Cartão) processado com sucesso');
    assert(env.startedSources[0].buffer === env.dummyBuffers['yellow_card.ogg'], 'N5: Buffer é yellow_card.ogg');
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(audio.pendingQueue.size === 0, 'N5: Fila limpa sem promessas pendentes');
  }

  // N6. F5 durante síntese
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'n6:pen' } }
    ];
    env.startedOscillators.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    // ID registrado imediatamente após agendamento (P1-A), ANTES do ended!
    assert(audio.wasHandled('n6:pen'), 'N6: ID registrado antes do ended');
    assert(env.sessionData.get('rumo10_sound_played:n6:pen') === '1', 'N6: Registrado no sessionStorage');

    // Simula F5 (reload de página durante síntese em andamento)
    env.startedOscillators.length = 0;
    const audioReload = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedOscillators.length === 0, 'N6: Zero osciladores sintetizados após F5 (sem replay)');
    assert(audioReload.pendingQueue.size === 0, 'N6: Fila vazia na nova página');
  }

  // N7. Mute durante síntese
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'n7:pen' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'n7:card' } }
    ];
    env.startedOscillators.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    // Muta durante a síntese
    audio.setMuted(true);
    assert(audio.prefs.muted === true, 'N7: Mute ativado durante síntese');
    assert(audio.wasHandled('n7:card'), 'N7: Itens subsequentes da fila marcados handled ao mutar');
    assert(audio.pendingQueue.size === 0, 'N7: Fila limpa ao mutar');

    // Desmuta depois
    audio.setMuted(false);
    await new Promise(r => setTimeout(r, 100));
    assert(env.startedSources.length === 0, 'N7: Nenhum som pendente disparado indevidamente após desmutar');
  }

  // N8. Suspensão durante a pausa de 250ms entre sons
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'n8:goal' } },
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 'n8:pen' } }
    ];
    env.startedSources.length = 0;
    env.startedOscillators.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    const goalSource = env.startedSources[0].source;
    goalSource.triggerEnd();

    // Suspende contexto logo após término do gol (durante a pausa)
    audio.ctx.state = 'suspended';
    await new Promise(r => setTimeout(r, 350));

    assert(env.startedOscillators.length === 0, 'N8: Próximo som NÃO dispara enquanto contexto estiver suspenso na pausa');
    assert(audio.pendingQueue.has('n8:pen'), 'N8: Pênalti retido na fila');

    // Retoma contexto via unlock
    audio.unlock();
    await new Promise(r => setTimeout(r, 80));

    assert(audio.ctx.state === 'running', 'N8: Contexto retomado');
    assert(env.startedOscillators.length === 1, 'N8: Pênalti inicia após retomada');
    env.startedOscillators[0].triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(audio.pendingQueue.size === 0, 'N8: Fila limpa');
  }

  // 21. Validando R5: Desbloqueio por Clique e Recuperação em Falha de Stop (Codex R5 - T1 a T7)
  console.log('\n21. Validando R5: Desbloqueio por Clique e Recuperação de Stop (Codex R5 - T1 a T7)...');

  // T1. Deadlock de retomada por clique
  {
    const env = createMockEnvironment();
    const preCtx = new env.mockWindow.AudioContext();
    preCtx.state = 'suspended';
    env.mockWindow.__rumo10AudioCtx = preCtx;

    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 't1:pen' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 't1:card' } }
    ];

    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    // 1º clique: desbloqueia contexto e inicia pênalti
    audio.unlock();
    await new Promise(r => setTimeout(r, 50));

    assert(audio.ctx.resumeCalls === 1, 'T1: 1ª chamada a resume() efetuada no 1º clique');
    assert(audio.ctx.state === 'running', 'T1: Contexto running após 1º clique');
    assert(audio.unlockingPromise === null, 'T1: unlockingPromise liberada após conclusão de resume() (desacoplada da fila)');

    // Suspende novamente o contexto durante a reprodução do sintetizador
    audio.ctx.state = 'suspended';

    // 2º clique do usuário para retomar
    audio.unlock();
    await new Promise(r => setTimeout(r, 50));

    assert(audio.ctx.resumeCalls === 2, 'T1: 2ª chamada a resume() efetuada no 2º clique (sem deadlock)');
    assert(audio.ctx.state === 'running', 'T1: Contexto voltou para running após 2º clique');

    // Conclui o pênalti via ended real
    const penOsc = env.startedOscillators[0];
    const tEnd = env.mockWindow.performance.now();
    penOsc.triggerEnd();

    // Aguarda o próximo som iniciar
    while (env.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStartCard = env.mockWindow.performance.now();
    const gap = tStartCard - tEnd;

    assert(gap >= 250, `T1: Espaçamento de 250ms respeitado após retomada por clique: ${gap.toFixed(1)}ms >= 250ms`);
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(audio.pendingQueue.size === 0, 'T1: Fila limpa');
  }

  // T2. Falha de stop() depois de start() com recuperação na 2ª tentativa
  {
    // T2.1 PENALTY_AWARDED
    const envPen = createMockEnvironment();
    const origCreateOsc = envPen.mockWindow.AudioContext.prototype.createOscillator;
    let penOsc = null;
    envPen.mockWindow.AudioContext.prototype.createOscillator = function() {
      const osc = origCreateOsc.call(this);
      osc.failFirstStop = true;
      penOsc = osc;
      return osc;
    };

    const elementsPen = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 't2:pen' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 't2:card' } }
    ];

    const audioPen = envPen.runScript(elementsPen);
    await new Promise(r => setTimeout(r, 50));

    assert(penOsc && penOsc.startCalls === 1, 'T2.1: start() ocorreu com sucesso no oscilador de pênalti');
    assert(penOsc && penOsc.stopCalls === 2 && penOsc.stopped, 'T2.1: 2ª tentativa de stop() no catch interrompeu com sucesso o oscilador');
    assert(audioPen.wasHandled('t2:pen'), 'T2.1: Evento com erro tratado como terminal');

    // Aguarda o próximo som (Cartão) após o erro
    while (envPen.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    assert(envPen.startedSources.length === 1, 'T2.1: Próximo som (Cartão) processado com sucesso');
    envPen.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));

    // T2.2 BALL_HIT_POST
    const envPost = createMockEnvironment();
    let postOscs = [];
    envPost.mockWindow.AudioContext.prototype.createOscillator = function() {
      const osc = origCreateOsc.call(this);
      osc.failFirstStop = true;
      postOscs.push(osc);
      return osc;
    };

    const elementsPost = [
      { dataset: { soundEvent: 'BALL_HIT_POST', soundEventId: 't2:post' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 't2:post:card' } }
    ];

    const audioPost = envPost.runScript(elementsPost);
    await new Promise(r => setTimeout(r, 50));

    assert(postOscs.length > 0 && postOscs[0].stopCalls === 2 && postOscs[0].stopped, 'T2.2: 2ª tentativa de stop() interrompeu o oscilador de BALL_HIT_POST');
    assert(audioPost.wasHandled('t2:post'), 'T2.2: Evento com erro tratado como terminal');

    while (envPost.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    assert(envPost.startedSources.length === 1, 'T2.2: Próximo som processado com sucesso');
    envPost.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
  }

  // T3. Nova suspensão durante PENALTY_AWARDED, retomada exclusivamente por clique
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 't3:pen' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 't3:card' } }
    ];

    env.startedSources.length = 0;
    env.startedOscillators.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedOscillators.length === 1, 'T3: Pênalti iniciado');
    const osc = env.startedOscillators[0];

    // Suspende durante execução
    audio.ctx.state = 'suspended';
    await new Promise(r => setTimeout(r, 500));

    assert(!osc.ended, 'T3: Oscilador não finaliza em suspensão');

    // Retomada acionada EXCLUSIVAMENTE por clique (audio.unlock()), sem chamada direta a ctx.resume()
    audio.unlock();
    await new Promise(r => setTimeout(r, 50));

    assert(audio.ctx.state === 'running', 'T3: Contexto retomado exclusivamente por clique');

    const tEnd = env.mockWindow.performance.now();
    osc.triggerEnd();

    while (env.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStartCard = env.mockWindow.performance.now();
    const gap = tStartCard - tEnd;

    assert(gap >= 250, `T3: Espaçamento regulamentar garantido após retomada por clique: ${gap.toFixed(1)}ms >= 250ms`);
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
  }

  // T4. Repetir com BALL_HIT_POST validando os dois osciladores e retomada por clique
  {
    const env = createMockEnvironment();
    const elements = [
      { dataset: { soundEvent: 'BALL_HIT_POST', soundEventId: 't4:post' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 't4:card' } }
    ];

    env.startedSources.length = 0;
    env.startedOscillators.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(env.startedOscillators.length === 2, 'T4: Dois osciladores iniciados para BALL_HIT_POST');
    const osc1 = env.startedOscillators[0];
    const osc2 = env.startedOscillators[1];

    // Suspende durante execução
    audio.ctx.state = 'suspended';
    await new Promise(r => setTimeout(r, 400));

    // Retomada exclusivamente por clique
    audio.unlock();
    await new Promise(r => setTimeout(r, 50));

    assert(audio.ctx.state === 'running', 'T4: Contexto retomado por clique');

    // Conclui o 1º oscilador
    osc1.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
    assert(audio.playing === true && env.startedSources.length === 0, 'T4: Fila continua bloqueada após 1º oscilador');

    // Conclui o 2º oscilador
    const tEnd = env.mockWindow.performance.now();
    osc2.triggerEnd();

    while (env.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    const tStart = env.mockWindow.performance.now();
    const gap = tStart - tEnd;

    assert(gap >= 250, `T4: Espaçamento de 250ms após término dos 2 osciladores: ${gap.toFixed(1)}ms >= 250ms`);
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
  }

  // T5. Falha única de stop() comprovando interrupção real (não apenas desconexão)
  {
    const env = createMockEnvironment();
    let oscCreated = null;
    const origCreateOsc = env.mockWindow.AudioContext.prototype.createOscillator;
    env.mockWindow.AudioContext.prototype.createOscillator = function() {
      const osc = origCreateOsc.call(this);
      osc.failFirstStop = true;
      oscCreated = osc;
      return osc;
    };

    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 't5:pen' } }
    ];

    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    assert(oscCreated.startCalls === 1, 'T5: Oscilador iniciou antes da falha de stop');
    assert(oscCreated.stopCalls === 2, 'T5: Exatamente 2 chamadas a stop() efetuadas');
    assert(oscCreated.stopped === true, 'T5: Oscilador está comprovadamente parado (stopped=true)');
    assert(oscCreated.connectedTo === null, 'T5: Oscilador desconectado com sucesso');
    assert(audio.pendingQueue.size === 0, 'T5: Fila esvaziada sem travamento');
  }

  // T6. Falha persistente de stop() com registro explícito da limitação
  {
    const env = createMockEnvironment();
    let oscPersistent = null;
    const origCreateOsc = env.mockWindow.AudioContext.prototype.createOscillator;
    env.mockWindow.AudioContext.prototype.createOscillator = function() {
      const osc = origCreateOsc.call(this);
      osc.failPersistentStop = true; // ambas as chamadas a stop() falham
      oscPersistent = osc;
      return osc;
    };

    let warnCaptured = null;
    const origWarn = console.warn;
    console.warn = (...args) => {
      warnCaptured = args.join(' ');
      origWarn(...args);
    };

    const elements = [
      { dataset: { soundEvent: 'PENALTY_AWARDED', soundEventId: 't6:pen' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 't6:card' } }
    ];

    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    console.warn = origWarn;

    assert(oscPersistent.startCalls === 1, 'T6: Oscilador foi iniciado');
    assert(oscPersistent.stopCalls === 2, 'T6: Tentou parar 2 vezes');
    assert(warnCaptured && warnCaptured.includes('parada da fonte não comprovada'), 'T6: Relatório explícito emitido informando que a parada não pôde ser comprovada');
    assert(oscPersistent.connectedTo === null, 'T6: Nós foram desconectados para silenciar áudio');
    assert(audio.wasHandled('t6:pen'), 'T6: Falha tratada como terminal');

    // Aguarda processamento do próximo som
    while (env.startedSources.length === 0) {
      await new Promise(r => setTimeout(r, 10));
    }
    assert(env.startedSources.length === 1, 'T6: Próximo som processado normalmente');
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
  }

  // T7. Múltiplos cliques durante a retomada não provocam chamadas concorrentes a resume()
  {
    const env = createMockEnvironment();
    let resolveResume = null;
    const preCtx = new env.mockWindow.AudioContext();
    preCtx.state = 'suspended';
    preCtx.resume = function() {
      this.resumeCalls++;
      return new Promise(resolve => {
        resolveResume = () => {
          this.state = 'running';
          resolve();
        };
      });
    };
    env.mockWindow.__rumo10AudioCtx = preCtx;

    const elements = [
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 't7:card' } }
    ];

    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    // Dispara 1º clique: inicia resume() (que fica pendente aguardando resolveResume)
    audio.unlock();
    assert(preCtx.resumeCalls === 1, 'T7: 1ª chamada a resume() disparada');

    // Dispara múltiplos cliques adicionais enquanto resume() está em andamento
    audio.unlock();
    audio.unlock();
    audio.unlock();
    audio.unlock();

    // Comprova que unlockingPromise impediu novas chamadas concorrentes a resume()
    assert(preCtx.resumeCalls === 1, 'T7: unlockingPromise impediu chamadas concorrentes a resume()');

    // Resolve a retomada
    resolveResume();
    await new Promise(r => setTimeout(r, 50));

    assert(audio.ctx.state === 'running', 'T7: Contexto transitou para running');
    assert(audio.unlockingPromise === null, 'T7: unlockingPromise liberada após conclusão');
    assert(env.startedSources.length === 1, 'T7: Som executado com sucesso');
    env.startedSources[0].source.triggerEnd();
    await new Promise(r => setTimeout(r, 50));
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
