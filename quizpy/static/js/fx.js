/* Efectos: sonidos sintetizados con Web Audio (sin archivos) y confeti. */
const Sound = (() => {
  let ctx = null;
  let muted = false;
  let musicTimer = null;
  try { muted = localStorage.getItem('quizpy-muted') === '1'; } catch (e) { /* sin almacenamiento */ }

  function audio() {
    if (!ctx) {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return null;
      ctx = new AC();
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  function tone(freq, at = 0, dur = 0.15, type = 'sine', vol = 0.15) {
    if (muted) return;
    const c = audio();
    if (!c) return;
    const t0 = c.currentTime + at;
    const osc = c.createOscillator();
    const gain = c.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t0);
    gain.gain.setValueAtTime(vol, t0);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    osc.connect(gain);
    gain.connect(c.destination);
    osc.start(t0);
    osc.stop(t0 + dur + 0.02);
  }

  const MELODY = [523, 659, 784, 659, 587, 698, 880, 698, 523, 659, 784, 1047, 988, 784, 659, 587];

  document.addEventListener('pointerdown', audio, { once: true });

  return {
    tick: () => tone(880, 0, 0.06, 'square', 0.04),
    join: () => { tone(660, 0, 0.1, 'triangle'); tone(990, 0.08, 0.14, 'triangle'); },
    correct: () => [523, 659, 784, 1047].forEach((f, i) => tone(f, i * 0.08, 0.2, 'triangle', 0.18)),
    wrong: () => { tone(233, 0, 0.25, 'sawtooth', 0.07); tone(175, 0.2, 0.4, 'sawtooth', 0.07); },
    gong: () => { tone(196, 0, 1.4, 'sine', 0.3); tone(392, 0, 0.9, 'sine', 0.08); },
    fanfare: () => [523, 523, 523, 659, 784, 1047].forEach((f, i) => tone(f, i * 0.15, i === 5 ? 0.9 : 0.14, 'square', 0.07)),
    music(on) {
      clearInterval(musicTimer);
      musicTimer = null;
      if (!on) return;
      let k = 0;
      musicTimer = setInterval(() => {
        const f = MELODY[k % MELODY.length];
        tone(f, 0, 0.2, 'triangle', 0.05);
        if (k % 2 === 0) tone(f / 2, 0, 0.35, 'sine', 0.06);
        k += 1;
      }, 240);
    },
    get musicOn() { return musicTimer !== null; },
    get muted() { return muted; },
    toggleMute() {
      muted = !muted;
      try { localStorage.setItem('quizpy-muted', muted ? '1' : '0'); } catch (e) { /* sin almacenamiento */ }
      return muted;
    },
  };
})();

const Confetti = (() => {
  const COLORS = ['#e21b3c', '#1368ce', '#d89e00', '#26890c', '#864cbf', '#ffd23f', '#ffffff'];
  let canvas = null;
  let ctx = null;
  let parts = [];
  let running = false;

  function resize() { canvas.width = innerWidth; canvas.height = innerHeight; }

  function loop() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    parts.forEach((p) => {
      p.x += p.vx; p.y += p.vy; p.vy += 0.04; p.rot += p.vr;
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);
      ctx.fillStyle = p.color;
      ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
      ctx.restore();
    });
    parts = parts.filter((p) => p.y < canvas.height + 30);
    if (parts.length) requestAnimationFrame(loop);
    else { running = false; ctx.clearRect(0, 0, canvas.width, canvas.height); }
  }

  return {
    burst(n = 220) {
      if (!canvas) {
        canvas = document.getElementById('confetti');
        if (!canvas) return;
        ctx = canvas.getContext('2d');
        resize();
        addEventListener('resize', resize);
      }
      for (let i = 0; i < n; i += 1) {
        parts.push({
          x: Math.random() * canvas.width,
          y: -20 - Math.random() * canvas.height * 0.6,
          vx: (Math.random() - 0.5) * 3,
          vy: 2 + Math.random() * 3,
          size: 7 + Math.random() * 9,
          rot: Math.random() * 6,
          vr: (Math.random() - 0.5) * 0.25,
          color: COLORS[Math.floor(Math.random() * COLORS.length)],
        });
      }
      if (!running) { running = true; loop(); }
    },
  };
})();
