(() => {
  'use strict';

  // Gerenciador de Áudio Web Audio API (Sintetizador Puro de Alta Qualidade)
  // Sem chamadas de rede externas, em conformidade estrita com Content-Security-Policy: default-src 'self'
  const AudioEngine = {
    ctx: null,
    prefs: { muted: false, volume: 0.7 },
    pendingQueue: [],
    unlockingPromise: null,

    init() {
      try {
        const saved = localStorage.getItem('rumo10_audio_prefs');
        if (saved) {
          const parsed = JSON.parse(saved);
          if (typeof parsed.muted === 'boolean') this.prefs.muted = parsed.muted;
          if (typeof parsed.volume === 'number') this.prefs.volume = Math.max(0, Math.min(1, parsed.volume));
        }
      } catch (_) {}

      // Cria ou reutiliza contexto global que sobrevive à substituição do DOM
      if (!window.__rumo10AudioCtx) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
          try {
            window.__rumo10AudioCtx = new AudioContextClass();
          } catch (_) {}
        }
      }
      this.ctx = window.__rumo10AudioCtx || null;
    },

    unlock() {
      if (!this.ctx) return Promise.resolve(false);
      if (this.ctx.state === 'running') return Promise.resolve(true);
      if (this.unlockingPromise) return this.unlockingPromise;

      this.unlockingPromise = this.ctx.resume().then(() => {
        this.unlockingPromise = null;
        this.flushPendingQueue();
        return true;
      }).catch(() => {
        this.unlockingPromise = null;
        return false;
      });

      return this.unlockingPromise;
    },

    flushPendingQueue() {
      if (!this.ctx || this.ctx.state !== 'running' || this.prefs.muted) return;
      while (this.pendingQueue.length > 0) {
        const eventKey = this.pendingQueue.shift();
        this.playDirect(eventKey);
      }
    },

    setMuted(muted) {
      this.prefs.muted = !!muted;
      this.savePrefs();
      this.updateUI();
      if (!this.prefs.muted) {
        this.unlock();
      } else {
        this.pendingQueue = [];
      }
    },

    setVolume(vol) {
      this.prefs.volume = Math.max(0, Math.min(1, vol));
      this.savePrefs();
      this.updateUI();
    },

    savePrefs() {
      try {
        localStorage.setItem('rumo10_audio_prefs', JSON.stringify(this.prefs));
      } catch (_) {}
    },

    enqueue(eventKey) {
      if (this.prefs.muted || !this.ctx) return;
      if (this.ctx.state === 'running') {
        this.playDirect(eventKey);
      } else {
        // Enfileira limitando a 5 eventos para evitar acúmulo ilimitado de sons antigos
        if (this.pendingQueue.length < 5) {
          this.pendingQueue.push(eventKey);
        }
      }
    },

    playDirect(eventKey) {
      if (this.prefs.muted || !this.ctx || this.ctx.state !== 'running') return;
      const now = this.ctx.currentTime;
      const vol = this.prefs.volume;

      switch (eventKey) {
        case 'MATCH_WHISTLE_START': // Dois apitos curtos e claros
          this.playWhistle(now, 2200, 0.12, vol);
          this.playWhistle(now + 0.16, 2400, 0.22, vol);
          break;

        case 'MATCH_WHISTLE_END': // Três apitos (dois curtos, um longo)
          this.playWhistle(now, 2200, 0.12, vol);
          this.playWhistle(now + 0.18, 2200, 0.12, vol);
          this.playWhistle(now + 0.38, 2400, 0.55, vol);
          break;

        case 'PLAYER_SUBSTITUTION_IN': // Tom ascendente elegante de entrada
          this.playChime(now, [440, 659.25, 880], 0.25, vol * 0.85);
          break;

        case 'PLAYER_SUBSTITUTION_OUT': // Tom descendente elegante de saída
          this.playChime(now, [880, 659.25, 523.25], 0.28, vol * 0.85);
          break;

        case 'GOAL_SCORED': // Acorde solene triunfante com brilho sonoro
          this.playChime(now, [523.25, 659.25, 783.99, 1046.50], 0.7, vol);
          break;

        case 'ASSIST_RECORDED': // Acorde límpido de assistência
          this.playChime(now, [587.33, 739.99, 880], 0.5, vol * 0.9);
          break;

        case 'CARD_YELLOW': // Apito curto com advertência
          this.playWhistle(now, 1900, 0.18, vol * 0.85);
          this.playTone(now + 0.20, 330, 0.22, 'triangle', vol * 0.5);
          break;

        case 'CARD_RED': // Apito penetrante com tom grave
          this.playWhistle(now, 2600, 0.40, vol);
          this.playTone(now + 0.15, 180, 0.50, 'sawtooth', vol * 0.6);
          break;

        case 'PENALTY_AWARDED': // Apito enérgico longo
          this.playWhistle(now, 2500, 0.35, vol);
          break;

        case 'BALL_HIT_POST': // Impacto metálico no poste
          this.playTone(now, 160, 0.15, 'triangle', vol * 0.8);
          this.playTone(now + 0.05, 80, 0.25, 'sine', vol * 0.9);
          break;
      }
    },

    playWhistle(startTime, freq, duration, gainVal) {
      if (!this.ctx) return;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, startTime);
      osc.frequency.exponentialRampToValueAtTime(freq * 0.95, startTime + duration);

      gain.gain.setValueAtTime(0, startTime);
      gain.gain.linearRampToValueAtTime(gainVal * 0.4, startTime + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

      osc.connect(gain);
      gain.connect(this.ctx.destination);
      osc.start(startTime);
      osc.stop(startTime + duration);
    },

    playTone(startTime, freq, duration, type, gainVal) {
      if (!this.ctx) return;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();
      osc.type = type || 'sine';
      osc.frequency.setValueAtTime(freq, startTime);

      gain.gain.setValueAtTime(gainVal * 0.3, startTime);
      gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

      osc.connect(gain);
      gain.connect(this.ctx.destination);
      osc.start(startTime);
      osc.stop(startTime + duration);
    },

    playChime(startTime, freqs, duration, gainVal) {
      if (!this.ctx) return;
      freqs.forEach((freq, idx) => {
        const noteStart = startTime + idx * 0.07;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(freq, noteStart);

        gain.gain.setValueAtTime(0, noteStart);
        gain.gain.linearRampToValueAtTime((gainVal / freqs.length) * 0.8, noteStart + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.001, noteStart + duration);

        osc.connect(gain);
        gain.connect(this.ctx.destination);
        osc.start(noteStart);
        osc.stop(noteStart + duration);
      });
    },

    updateUI() {
      const btn = document.querySelector('[data-action="toggle-audio"]');
      if (btn) {
        btn.setAttribute('aria-pressed', (!this.prefs.muted).toString());
        btn.classList.toggle('is-muted', this.prefs.muted);
        const icon = btn.querySelector('.audio-icon');
        if (icon) {
          icon.innerHTML = this.prefs.muted
            ? '<path d="M11 5L6 9H2v6h4l5 4V5zM23 9l-6 6M17 9l6 6"/>'
            : '<path d="M11 5L6 9H2v6h4l5 4V5zM15.54 8.46a5 5 0 0 1 0 7.07M19.07 4.93a10 10 0 0 1 0 14.14"/>';
        }
      }
    }
  };

  // Inicializa o Engine
  AudioEngine.init();
  window.__rumo10Audio = AudioEngine;

  // Executa sons emitidos pelo servidor se ainda não foram reproduzidos nesta tela
  function scanAndPlayServerEvents() {
    document.querySelectorAll('[data-sound-event]').forEach(el => {
      const eventKey = el.dataset.soundEvent;
      const eventId = el.dataset.soundEventId || (eventKey + ':' + window.location.pathname);
      const storageKey = 'rumo10_sound_played:' + eventId;
      try {
        if (!sessionStorage.getItem(storageKey)) {
          sessionStorage.setItem(storageKey, '1');
          AudioEngine.enqueue(eventKey);
        }
      } catch (_) {
        AudioEngine.enqueue(eventKey);
      }
    });
  }

  // Desbloqueia na interação do usuário (click/touch/submit) e descarrega a fila com garantia da promessa
  ['click', 'pointerdown', 'keydown', 'submit'].forEach(ev => {
    window.addEventListener(ev, () => {
      AudioEngine.unlock().then(() => {
        scanAndPlayServerEvents();
      });
    }, { capture: true });
  });

  // Monitora controles de áudio na interface
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-action="toggle-audio"]');
    if (btn) {
      e.preventDefault();
      AudioEngine.setMuted(!AudioEngine.prefs.muted);
    }
  });

  // Executa na carga da página
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      AudioEngine.updateUI();
      scanAndPlayServerEvents();
    });
  } else {
    AudioEngine.updateUI();
    scanAndPlayServerEvents();
  }
})();
