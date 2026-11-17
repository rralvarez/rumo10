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
    MATCH_WHISTLE_END: 100, MATCH_HALFTIME: 95, MATCH_WHISTLE_START: 90,
    GOAL_SCORED: 85, NO_GOAL: 80, CARD_RED: 75,
    ASSIST_RECORDED: 70, CARD_YELLOW: 65,
    PLAYER_SUBSTITUTION_IN: 60, PLAYER_SUBSTITUTION_OUT: 55
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
      for (let index = 0; index < count; index++) {
        const source = this.ctx.createBufferSource();
        const gain = this.ctx.createGain();
        source.buffer = buffer;
        gain.gain.value = level;
        source.connect(gain);
        gain.connect(this.master);
        source.onended = () => { source.disconnect(); gain.disconnect(); };
        source.start(startAt + index * WHISTLE_GAP_SECONDS);
      }
      return true;
    },

    async flushPendingQueue() {
      if (!this.ctx || this.ctx.state !== 'running' || this.prefs.muted || this.playing) return;
      if (this.pendingQueue.size === 0) return;
      // Muitos eventos históricos chegam no mesmo DOM. Um único efeito por
      // atualização evita uma rajada de sons, sem alterar o estado do jogo.
      const candidates = [...this.pendingQueue].sort((a, b) =>
        (EVENT_PRIORITY[b[1]] || 0) - (EVENT_PRIORITY[a[1]] || 0));
      const [id, key] = candidates[0];
      this.pendingQueue.clear();
      for (const [otherId] of candidates.slice(1)) this.markHandled(otherId);
      this.playing = true;
      try {
        const started = await this.playDirect(key);
        if (started) this.markHandled(id);
      } catch (_) {
        // Arquivo ausente ou não decodificável: não simular efeito sintético.
        // A falha permanece recuperável na próxima interação/visita.
      } finally {
        this.playing = false;
        if (this.pendingQueue.size) this.flushPendingQueue();
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
    document.querySelectorAll('[data-sound-event][data-sound-event-id]').forEach(element => {
      AudioEngine.enqueue(element.dataset.soundEventId, element.dataset.soundEvent);
    });
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
