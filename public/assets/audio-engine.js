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
    PLAYER_SUBSTITUTION_OUT: { file: 'substitution_out.ogg' }
  });
  const WHISTLE_GAP_SECONDS = 0.18; // Ponto inicial para homologação auditiva.
  const EVENT_PRIORITY = Object.freeze({
    GOAL_SCORED: 100,
    ASSIST_RECORDED: 90,
    NO_GOAL: 85,
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
      if (this.handled.has(id)) return true;
      try { return sessionStorage.getItem('rumo10_sound_played:' + id) === '1'; }
      catch (_) { return false; }
    },

    enqueue(id, key) {
      if (!this.ctx || !SOUNDS[key] || !id || this.wasHandled(id)) return;
      if (this.prefs.muted) { this.markHandled(id); return; }
      this.pendingQueue.set(id, key);
      this.flushPendingQueue();
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

    async playDirect(key) {
      const effect = SOUNDS[key];
      if (!effect || !this.ctx || !this.master) return false;
      const buffer = await this.bufferFor(effect.file);
      if (this.prefs.muted || this.prefs.volume <= 0 || this.ctx.state !== 'running') return true;
      const count = effect.repeats || 1;
      // A divisão do ganho mantém margem para a soma dos disparos sobrepostos.
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
              resolve(true);
            }
          };
          source.start(startAt + index * WHISTLE_GAP_SECONDS);
        }
      });
    },

    async flushPendingQueue() {
      if (!this.ctx || this.ctx.state !== 'running' || this.prefs.muted || this.playing) return;
      if (this.pendingQueue.size === 0) return;

      const entry = this.pendingQueue.entries().next().value;
      if (!entry) return;
      const [id, key] = entry;
      this.pendingQueue.delete(id);
      this.playing = true;

      try {
        await this.playDirect(key);
        this.markHandled(id);
      } catch (_) {
        // Arquivo ausente ou erro de decodificação: marcar manipulado para evitar loop
        this.markHandled(id);
      } finally {
        this.playing = false;
        if (this.pendingQueue.size > 0 && !this.prefs.muted && this.ctx && this.ctx.state === 'running') {
          // Espaçamento natural entre eventos contemporâneos (ex: gol seguido do apito final)
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

    // Permite enfileirar até 2 eventos contemporâneos legítimos (ex: lance de ação + apito de período).
    // Quaisquer excedentes são marcados como tratados para prevenir rajadas.
    const toQueue = ordered.slice(0, 2);
    const toDrop = ordered.slice(2);
    for (const item of toDrop) {
      AudioEngine.markHandled(item.id);
    }
    for (const item of toQueue) {
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
