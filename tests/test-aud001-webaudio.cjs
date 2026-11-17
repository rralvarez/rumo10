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
    }
    connect(dest) {
      this.connectedTo = dest;
    }
    disconnect() {
      this.connectedTo = null;
    }
    start(time) {
      this.startTime = time;
      startedOscillators.push(this);
    }
    stop(time) {
      this.stopTime = time;
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

  // 12. Validação da Fila de 3 Eventos Contemporâneos (Gol + Cartão + Apito Final aos 90 min)
  console.log('\n12. Validando Fila Contemporânea de 3 Eventos Legítimos (Gol + Cartão + Apito Final)...');
  {
    const env = createMockEnvironment();
    // Cenário aos 90 min: gol marcado + cartão na jogada + apito final de encerramento + evento excedente (4º de menor prioridade)
    const elements = [
      { dataset: { soundEvent: 'MATCH_WHISTLE_END', soundEventId: 'whistle:90:triple' } },
      { dataset: { soundEvent: 'GOAL_SCORED', soundEventId: 'goal:90:triple' } },
      { dataset: { soundEvent: 'CARD_YELLOW', soundEventId: 'card:90:triple' } },
      { dataset: { soundEvent: 'MATCH_WHISTLE_START', soundEventId: 'burst:90:drop' } }, // 4º evento de menor prioridade deve ser descartado com segurança
    ];

    env.startedSources.length = 0;
    const audio = env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));

    // O 4º evento excedente foi descartado e marcado como handled para proteção de rajadas
    assert(env.sessionData.get('rumo10_sound_played:burst:90:drop') === '1', '4º evento excedente descartado e marcado handled (proteção contra rajada)');

    // 1º evento reproduzido: GOAL_SCORED (prioridade 100)
    assert(env.startedSources.length === 1, 'Primeiro evento da trinca inicia reprodução');
    assert(env.startedSources[0].buffer === env.dummyBuffers['goal.ogg'], '1º a tocar: GOAL_SCORED (maior prioridade)');

    // Conclui o gol -> deve iniciar o 2º evento: CARD_YELLOW (prioridade 60)
    const firstSource = env.startedSources[0].source;
    firstSource.triggerEnd();
    await new Promise(r => setTimeout(r, 350));

    assert(env.startedSources.length === 2, '2º evento inicia após término do gol com espaçamento regulamentar');
    assert(env.startedSources[1].buffer === env.dummyBuffers['yellow_card.ogg'], '2º a tocar: CARD_YELLOW (prioridade intermediária)');

    // Conclui o cartão -> deve iniciar o 3º evento: MATCH_WHISTLE_END (prioridade 50, 3 apitos)
    const secondSource = env.startedSources[1].source;
    secondSource.triggerEnd();
    await new Promise(r => setTimeout(r, 350));

    assert(env.startedSources.length === 5, '3º evento (MATCH_WHISTLE_END) reproduz seus 3 apitos sobrepostos (total de 5 sources)');
    assert(env.startedSources[2].buffer === env.dummyBuffers['match_whistle.ogg'], '3º a tocar: MATCH_WHISTLE_END pulso 1');
    assert(env.startedSources[3].buffer === env.dummyBuffers['match_whistle.ogg'], '3º a tocar: MATCH_WHISTLE_END pulso 2');
    assert(env.startedSources[4].buffer === env.dummyBuffers['match_whistle.ogg'], '3º a tocar: MATCH_WHISTLE_END pulso 3');

    // Todos os 3 eventos legítimos foram devidamente registrados como tratados
    assert(env.sessionData.get('rumo10_sound_played:goal:90:triple') === '1', 'Gol registrado como tocado');
    assert(env.sessionData.get('rumo10_sound_played:card:90:triple') === '1', 'Cartão registrado como tocado');

    // Conclui os apitos para marcar handled no apito final
    const whistleSources = env.startedSources.filter(s => s.buffer === env.dummyBuffers['match_whistle.ogg']);
    whistleSources.forEach(s => s.source.triggerEnd());
    await new Promise(r => setTimeout(r, 50));

    assert(env.sessionData.get('rumo10_sound_played:whistle:90:triple') === '1', 'Apito final registrado como tocado');

    // F5 após tríplice evento: NENHUM som é re-executado
    env.startedSources.length = 0;
    env.runScript(elements);
    await new Promise(r => setTimeout(r, 50));
    assert(env.startedSources.length === 0, 'Deduplicação no F5 após tríplice evento mantém silêncio absoluto');
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

    // Aguardar término do efeito sintetizado (~300ms) + espaçamento regulamentar de 250ms
    await new Promise(r => setTimeout(r, 600));

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
