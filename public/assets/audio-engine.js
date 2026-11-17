(() => {
  'use strict';

  // AUD-001: sons naturais, com arquivos hospedados no próprio Rumo10.
  // O mesmo buffer do apito é disparado 1, 2 ou 3 vezes, em sobreposição.
  const SOUNDS = Object.freeze({
    MATCH_WHISTLE_START: { file: 'match_whistle.ogg', repeats: 1 },
    MATCH_HALFTIME: { file: 'match_whistle.ogg', repeats: 2 },
    MATCH_WHISTLE_END: { file: 'match_whistle.ogg', repeats: 3 },
    GOAL_SCORED: { file: 'goal.ogg' },
    NO_GOAL: { file: 'no_goal.ogg' },
    ASSIST_RECORDED: { file: 'assist.ogg' },
    CARD_YELLOW: { file: 'yellow_card.ogg' },
    CARD_RED: { file: 'red_card.ogg' },
    PLAYER_SUBSTITUTION_IN: { file: 'substitution_in.ogg' },
    PLAYER_SUBSTITUTION_OUT: { file: 'substitution_out.ogg' },
    // Efeitos legados sintetizados mantidos para compatibilidade retroativa
    BALL_HIT_POST: { synth: 'ball_hit_post', duration: 0.30 },
    PENALTY_AWARDED: { synth: 'penalty_awarded', duration: 0.35 }
  });
  const WHISTLE_GAP_SECONDS = 0.18; // Ponto inicial para homologação auditiva.
  const EVENT_PRIORITY = Object.freeze({
    GOAL_SCORED: 100,
    ASSIST_RECORDED: 90,
    BALL_HIT_POST: 88,
    NO_GOAL: 85,
    PENALTY_AWARDED: 82,
    CARD_RED: 80,
    CARD_YELLOW: 75,
    PLAYER_SUBSTITUTION_IN: 70,
    PLAYER_SUBSTITUTION_OUT: 65,
    MATCH_WHISTLE_END: 50,
    MATCH_HALFTIME: 45,
    MATCH_WHISTLE_START: 40
  });

  const AudioEngine = {
    ctx: null,
    master: null,
    prefs: { muted: false, volume: 0.7 },
    pendingQueue: new Map(),
    handled: new Set(),
    inFlight: new Set(),
    cachedBuffers: new Map(),
    unlockingPromise: null,
    playing: false,

    init() {
      try {
        const saved = JSON.parse(localStorage.getItem('rumo10_audio_prefs') || 'null');
        if (saved && typeof saved.muted === 'boolean') this.prefs.muted = saved.muted;
        if (saved && Number.isFinite(saved.volume)) {
          this.prefs.volume = Math.max(0, Math.min(1, saved.volume));
        }
      } catch (_) {}
      try {
        if (!window.__rumo10AudioCtx) {
          const AudioContextClass = window.AudioContext || window.webkitAudioContext;
          if (AudioContextClass) window.__rumo10AudioCtx = new AudioContextClass();
        }
        this.ctx = window.__rumo10AudioCtx || null;
        if (this.ctx) {
          this.master = this.ctx.createGain();
          this.master.connect(this.ctx.destination);
          this.updateMasterVolume();
        }
      } catch (_) { this.ctx = null; this.master = null; }
    },

    updateMasterVolume() {
      if (!this.master || !this.ctx) return;
      const level = this.prefs.muted ? 0 : Math.min(0.65, this.prefs.volume * 0.65);
      this.master.gain.setTargetAtTime(level, this.ctx.currentTime, 0.008);
    },

    savePrefs() {
      try { localStorage.setItem('rumo10_audio_prefs', JSON.stringify(this.prefs)); } catch (_) {}
    },

    setMuted(muted) {
      this.prefs.muted = !!muted;
      this.savePrefs();
      this.updateMasterVolume();
      this.updateUI();
      if (this.prefs.muted) {
        for (const id of this.inFlight) this.markHandled(id);
        for (const id of this.pendingQueue.keys()) this.markHandled(id);
        this.pendingQueue.clear();
      } else {
        this.unlock();
      }
    },

    setVolume(volume) {
      this.prefs.volume = Math.max(0, Math.min(1, Number(volume) || 0));
      this.savePrefs();
      this.updateMasterVolume();
      this.updateUI();
    },

    unlock() {
      if (!this.ctx) return;
      if (this.ctx.state === 'running') { this.flushPendingQueue(); return; }
      if (this.unlockingPromise) return;
      this.unlockingPromise = this.ctx.resume()
        .then(() => this.flushPendingQueue())
        .catch(() => {})
        .finally(() => { this.unlockingPromise = null; });
    },

    markHandled(id) {
      this.handled.add(id);
      try { sessionStorage.setItem('rumo10_sound_played:' + id, '1'); } catch (_) {}
    },

    wasHandled(id) {
      if (this.handled.has(id) || this.inFlight.has(id)) return true;
      try { return sessionStorage.getItem('rumo10_sound_played:' + id) === '1'; }
      catch (_) { return false; }
    },

    enqueue(id, key) {
      if (!this.ctx || !SOUNDS[key] || !id || this.wasHandled(id)) return;
      if (this.prefs.muted) { this.markHandled(id); return; }
      this.pendingQueue.set(id, key);
      this.flushPendingQueue();
    },

    playWhistle(startTime, freq, duration, gainVal) {
      if (!this.ctx || !this.master) return;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, startTime);
      osc.frequency.exponentialRampToValueAtTime(freq * 0.95, startTime + duration);

      gain.gain.setValueAtTime(0, startTime);
      gain.gain.linearRampToValueAtTime(gainVal * 0.4, startTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

      osc.connect(gain);
      gain.connect(this.master);
      osc.start(startTime);
      osc.stop(startTime + duration);
    },

    playTone(startTime, freq, duration, type, gainVal) {
      if (!this.ctx || !this.master) return;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = type || 'sine';
      osc.frequency.setValueAtTime(freq, startTime);

      gain.gain.setValueAtTime(gainVal * 0.3, startTime);
      gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

      osc.connect(gain);
      gain.connect(this.master);
      osc.start(startTime);
      osc.stop(startTime + duration);
    },

    async bufferFor(file) {
      if (!this.cachedBuffers.has(file)) {
        const promise = fetch('/assets/audio/' + file, { credentials: 'same-origin' })
          .then(response => {
            if (!response.ok) throw new Error('Audio asset unavailable: ' + file);
            return response.arrayBuffer();
          })
          .then(bytes => this.ctx.decodeAudioData(bytes))
          .catch(error => {
            this.cachedBuffers.delete(file);
            throw error;
          });
        this.cachedBuffers.set(file, promise);
      }
      return this.cachedBuffers.get(file);
    },

    async playDirect(key, id) {
      const effect = SOUNDS[key];
      if (!effect || !this.ctx || !this.master) return { status: 'failed' };
      if (this.prefs.muted || this.prefs.volume <= 0) {
        if (id) this.markHandled(id);
        return { status: 'muted' };
      }

      // Efeitos legados sintetizados (BALL_HIT_POST e PENALTY_AWARDED)
      if (effect.synth) {
        if (this.ctx.state !== 'running') return { status: 'suspended' };
        const now = this.ctx.currentTime + 0.02;
        const vol = this.prefs.volume;
        if (effect.synth === 'penalty_awarded') {
          this.playWhistle(now, 2500, 0.35, vol);
        } else if (effect.synth === 'ball_hit_post') {
          this.playTone(now, 160, 0.15, 'triangle', vol * 0.8);
          this.playTone(now + 0.05, 80, 0.25, 'sine', vol * 0.9);
        }
        if (id) this.markHandled(id);
        const duration = effect.duration || 0.35;
        return new Promise(resolve => setTimeout(() => resolve({ status: 'scheduled' }), duration * 1000));
      }

      // Efeitos naturais com arquivos OGG
      const buffer = await this.bufferFor(effect.file);

      // P2 Residual Mute: se o evento foi cancelado durante o download assíncrono (ex: mute ativado)
      if (id && this.handled.has(id)) {
        return { status: 'cancelled' };
      }

      // P1-B: Verifica se o contexto foi suspenso durante o fetch assíncrono do arquivo
      if (this.prefs.muted || this.prefs.volume <= 0) {
        if (id) this.markHandled(id);
        return { status: 'muted' };
      }
      if (this.ctx.state !== 'running') {
        return { status: 'suspended' };
      }

      const count = effect.repeats || 1;
      const level = 1 / count;
      const startAt = this.ctx.currentTime + 0.02;

      return new Promise((resolve) => {
        let completed = 0;
        for (let index = 0; index < count; index++) {
          const source = this.ctx.createBufferSource();
          const gain = this.ctx.createGain();
          source.buffer = buffer;
          gain.gain.value = level;
          source.connect(gain);
          gain.connect(this.master);
          source.onended = () => {
            try { source.disconnect(); gain.disconnect(); } catch (_) {}
            completed++;
            if (completed === count) {
              resolve({ status: 'scheduled' });
            }
          };
          source.start(startAt + index * WHISTLE_GAP_SECONDS);
        }
        // P1-A: Registra como manipulado assim que o primeiro disparo foi agendado com sucesso
        if (id) {
          this.markHandled(id);
        }
      });
    },

    async flushPendingQueue() {
      if (!this.ctx || this.ctx.state !== 'running' || this.prefs.muted || this.playing) return;
      if (this.pendingQueue.size === 0) return;

      const entry = this.pendingQueue.entries().next().value;
      if (!entry) return;
      const [id, key] = entry;

      this.inFlight.add(id);
      this.playing = true;

      try {
        const result = await this.playDirect(key, id);
        if (result && result.status === 'suspended') {
          // P1-B: O contexto foi suspenso antes do agendamento; preserva na fila pendente
          // sem marcar como tratado, para tocar no próximo desbloqueio
          this.inFlight.delete(id);
          this.playing = false;
          return;
        }
        // Agendado ou mudo: remove da fila pendente
        this.pendingQueue.delete(id);
      } catch (_) {
        // Falha terminal (ex: 404 ou erro de decode): marca manipulado e remove da fila
        this.markHandled(id);
        this.pendingQueue.delete(id);
      } finally {
        this.inFlight.delete(id);
        this.playing = false;
        if (this.pendingQueue.size > 0 && !this.prefs.muted && this.ctx && this.ctx.state === 'running') {
          // Espaçamento regulamentar de 250ms entre eventos contemporâneos sucessivos
          setTimeout(() => this.flushPendingQueue(), 250);
        }
      }
    },

    updateUI() {
      const btn = document.querySelector('[data-action="toggle-audio"]');
      if (!btn) return;
      btn.setAttribute('aria-pressed', (!this.prefs.muted).toString());
      btn.classList.toggle('is-muted', this.prefs.muted);
      const icon = btn.querySelector('.audio-icon');
      if (icon) {
        icon.innerHTML = this.prefs.muted
          ? '<path d="M11 5L6 9H2v6h4l5 4V5zM23 9l-6 6M17 9l6 6"/>'
          : '<path d="M11 5L6 9H2v6h4l5 4V5zM15.54 8.46a5 5 0 0 1 0 7.07M19.07 4.93a10 10 0 0 1 0 14.14"/>';
      }
    }
  };

  AudioEngine.init();
  window.__rumo10Audio = AudioEngine;

  ['click', 'pointerdown', 'keydown', 'submit'].forEach(name => {
    window.addEventListener(name, () => AudioEngine.unlock(), { capture: true });
  });

  function scanServerEvents() {
    const unhandled = [];
    document.querySelectorAll('[data-sound-event][data-sound-event-id]').forEach(element => {
      const id = element.dataset.soundEventId;
      const key = element.dataset.soundEvent;
      const isHistorical = element.dataset.soundHistorical === '1';
      if (!id || !SOUNDS[key]) return;
      if (AudioEngine.wasHandled(id)) return;
      if (isHistorical) {
        AudioEngine.markHandled(id);
        return;
      }
      unhandled.push({ id, key, element });
    });

    if (!unhandled.length) return;

    // Deduplica por ID caso o mesmo evento apareça no feedback e no matchSounds
    const uniqueMap = new Map();
    for (const item of unhandled) {
      if (!uniqueMap.has(item.id)) {
        uniqueMap.set(item.id, item);
      }
    }

    const ordered = [...uniqueMap.values()].sort((a, b) =>
      (EVENT_PRIORITY[b.key] || 0) - (EVENT_PRIORITY[a.key] || 0));

    // Todos os eventos contemporâneos legítimos (já filtrados de históricos e deduplicados por ID)
    // são enfileirados na ordem de prioridade para reprodução sequencial com espaçamento de 250ms.
    for (const item of ordered) {
      AudioEngine.enqueue(item.id, item.key);
    }
  }

  document.addEventListener('click', event => {
    const btn = event.target.closest('[data-action="toggle-audio"]');
    if (btn) {
      event.preventDefault();
      AudioEngine.setMuted(!AudioEngine.prefs.muted);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      AudioEngine.updateUI();
      scanServerEvents();
    });
  } else {
    AudioEngine.updateUI();
    scanServerEvents();
  }
})();
