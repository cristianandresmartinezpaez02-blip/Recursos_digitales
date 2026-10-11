/* Pantalla del anfitrión (proyector). */
(() => {
  const socket = io();
  const $ = (id) => document.getElementById(id);
  const SHAPES = ['▲', '◆', '●', '■', '★', '⬢'];
  const TYPE_LABEL = {
    quiz: 'Quiz', multi: 'Varias correctas', tf: 'Verdadero o falso', type: 'Escribe la respuesta', poll: 'Encuesta',
  };
  const STORE = 'quizpy-host';
  let timer = null;
  let readyTimer = null;
  let lastCount = 0;
  let current = null;

  function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined && text !== null) e.textContent = text;
    return e;
  }
  function show(id) {
    document.querySelectorAll('.screen').forEach((s) => { s.hidden = s.id !== id; });
  }
  function stopTimers() {
    clearInterval(timer);
    clearInterval(readyTimer);
    $('getready').hidden = true;
  }
  function readStore() {
    try { return JSON.parse(sessionStorage.getItem(STORE)); } catch (e) { return null; }
  }

  // ---------------------------------------------------------------- conexión
  socket.on('connect', () => {
    const saved = readStore();
    if (saved && saved.quiz_id === QUIZ_ID) socket.emit('host_rejoin', saved);
    else socket.emit('host_create', { quiz_id: QUIZ_ID });
  });
  socket.on('rejoin_failed', () => {
    sessionStorage.removeItem(STORE);
    socket.emit('host_create', { quiz_id: QUIZ_ID });
  });
  socket.on('error_msg', (d) => alert(d.message));

  socket.on('game_created', (d) => {
    sessionStorage.setItem(STORE, JSON.stringify({ quiz_id: QUIZ_ID, pin: d.pin, host_key: d.host_key }));
    const url = JOIN_BASE + 'play?pin=' + d.pin;
    $('pin').textContent = d.pin.slice(0, 3) + ' ' + d.pin.slice(3);
    $('join-url').textContent = JOIN_BASE.replace(/^https?:\/\//, '').replace(/\/$/, '');
    $('quiz-title').textContent = d.title;
    $('qr').innerHTML = '';
    if (window.QRCode) new QRCode($('qr'), { text: url, width: 180, height: 180 });
    else $('qr').textContent = url;
    show(d.state === 'lobby' ? 'screen-lobby' : 'screen-resync');
  });

  // ------------------------------------------------------------------- lobby
  socket.on('player_list', (d) => {
    const box = $('players');
    box.innerHTML = '';
    $('player-count').textContent = d.players.length;
    $('opt-lock').checked = d.locked;
    $('btn-start').disabled = d.players.length === 0;
    if (!d.players.length) box.appendChild(el('p', 'waiting', 'Esperando jugadores…'));
    d.players.forEach((p) => {
      const chip = el('button', 'player-chip' + (p.online ? '' : ' offline'), p.avatar + ' ' + p.name);
      chip.title = 'Expulsar a ' + p.name;
      chip.onclick = () => {
        if (confirm('¿Expulsar a ' + p.name + '?')) socket.emit('kick', { player_id: p.id });
      };
      box.appendChild(chip);
    });
    if (d.players.length > lastCount && !$('screen-lobby').hidden) Sound.join();
    lastCount = d.players.length;
  });

  $('opt-lock').onchange = (e) => socket.emit('lock', { locked: e.target.checked });
  $('btn-start').onclick = () => socket.emit('next', { shuffle: $('opt-shuffle').checked });

  // ---------------------------------------------------------------- pregunta
  socket.on('question', (q) => {
    stopTimers();
    Sound.music(false);
    $('btn-music').classList.remove('on');
    current = q;
    const deadline = Date.now() + q.remaining * 1000;

    $('q-counter').textContent = `Pregunta ${q.index + 1} de ${q.total}`;
    let badge = TYPE_LABEL[q.type];
    if (q.points === 2) badge += ' · ⚡ Puntos dobles';
    if (q.points === 0 && q.type !== 'poll') badge += ' · Sin puntos';
    $('q-badge').textContent = badge;
    $('q-text').textContent = q.text;
    const img = $('q-image');
    img.hidden = !q.image;
    if (q.image) img.src = q.image; else img.removeAttribute('src');
    $('answer-count').textContent = '0';

    const grid = $('q-answers');
    grid.innerHTML = '';
    grid.className = 'answers-grid' + (q.options.length > 4 ? ' three' : '');
    if (q.type === 'type') grid.appendChild(el('div', 'type-hint', '⌨️ Escribe la respuesta en tu dispositivo'));
    q.options.forEach((o, i) => {
      const tile = el('div', 'answer-tile c' + i);
      tile.append(el('span', 'shape', SHAPES[i]), el('span', 'label', o));
      grid.appendChild(tile);
    });
    show('screen-question');

    if (q.countdown > 0.3) getReady(q, () => runTimer(deadline));
    else runTimer(deadline);
  });

  function getReady(q, done) {
    $('gr-label').textContent = `Pregunta ${q.index + 1} de ${q.total}`;
    $('gr-text').textContent = q.text;
    let n = Math.ceil(q.countdown);
    $('gr-num').textContent = n;
    $('getready').hidden = false;
    readyTimer = setInterval(() => {
      n -= 1;
      if (n <= 0) {
        clearInterval(readyTimer);
        $('getready').hidden = true;
        done();
      } else {
        $('gr-num').textContent = n;
      }
    }, 1000);
  }

  function runTimer(deadline) {
    let last = null;
    const tick = () => {
      const left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      if (left !== last) {
        $('q-timer').textContent = left;
        $('q-timer').classList.toggle('urgent', left <= 5);
        if (left <= 5 && left > 0) Sound.tick();
        last = left;
      }
      if (left <= 0) clearInterval(timer);
    };
    tick();
    timer = setInterval(tick, 200);
  }

  socket.on('answer_count', (d) => { $('answer-count').textContent = d.count; });
  $('btn-skip').onclick = () => socket.emit('next');

  // -------------------------------------------------------------- resultados
  socket.on('question_end', (d) => {
    stopTimers();
    Sound.gong();
    $('r-text').textContent = current ? current.text : '';
    const bars = $('r-bars');
    bars.innerHTML = '';
    const max = Math.max(1, ...d.distribution.map((x) => x.count));
    if (!d.distribution.length) bars.appendChild(el('p', 'waiting', 'Nadie respondió 😴'));

    d.distribution.forEach((x, i) => {
      const isType = d.type === 'type';
      const color = isType ? (x.correct ? 'c3' : 'cgray') : 'c' + i;
      const col = el('div', 'bar-col' + (d.type !== 'poll' && !x.correct ? ' dim' : ''));
      const area = el('div', 'bar-area');
      const bar = el('div', 'bar ' + color);
      bar.style.height = '0%';
      area.appendChild(bar);
      const label = el('div', 'bar-label ' + color);
      label.append(el('span', 'shape', isType ? (x.correct ? '✔' : '✖') : SHAPES[i]), el('span', 'txt', x.label));
      if (x.correct && d.type !== 'poll' && !isType) label.appendChild(el('span', 'check', '✔'));
      col.append(el('div', 'bar-count', x.count), area, label);
      bars.appendChild(col);
      requestAnimationFrame(() => requestAnimationFrame(() => {
        bar.style.height = Math.max(3, (x.count / max) * 100) + '%';
      }));
    });

    $('r-correct').textContent = d.correct_text ? 'Respuesta correcta: ' + d.correct_text : '';
    let summary = `${d.answered} de ${d.total} respondieron`;
    if (d.type !== 'poll') summary += ` · ${d.correct_count} acertaron`;
    $('r-summary').textContent = summary;
    $('btn-after-results').textContent = d.is_last ? 'Ver podio 🏆' : 'Ver clasificación';
    show('screen-results');
  });
  $('btn-after-results').onclick = () => socket.emit('next');

  // ---------------------------------------------------------- clasificación
  socket.on('leaderboard', (d) => {
    const list = $('lb-list');
    list.innerHTML = '';
    d.top.forEach((p, i) => {
      const row = el('div', 'lb-row');
      row.style.animationDelay = i * 0.12 + 's';
      let delta = el('span', 'delta');
      if (p.delta > 0) delta = el('span', 'delta up', '▲ ' + p.delta);
      if (p.delta < 0) delta = el('span', 'delta down', '▼ ' + -p.delta);
      row.append(
        el('span', 'rank', p.rank),
        el('span', 'avatar', p.avatar),
        el('span', 'name', p.name),
        el('span', 'streak', p.streak >= 3 ? '🔥 ' + p.streak : ''),
        delta,
        el('span', 'score', p.score),
      );
      list.appendChild(row);
    });
    const left = d.total - d.index - 1;
    $('lb-sub').textContent = left === 1 ? 'Queda 1 pregunta' : `Quedan ${left} preguntas`;
    show('screen-leaderboard');
  });
  $('btn-next-q').onclick = () => socket.emit('next');

  // ------------------------------------------------------------------ podio
  socket.on('game_over', (d) => {
    stopTimers();
    sessionStorage.removeItem(STORE);
    $('final-title').textContent = d.title;
    const podium = $('podium');
    podium.innerHTML = '';
    const delays = [1.6, 0.8, 0];
    [1, 0, 2].forEach((idx) => {
      const p = d.podium[idx];
      if (!p) return;
      const place = el('div', 'podium-place place-' + (idx + 1));
      place.style.animationDelay = delays[idx] + 's';
      place.append(
        el('div', 'podium-avatar', p.avatar),
        el('div', 'podium-name', p.name),
        el('div', 'podium-score', p.score + ' pts · ' + p.correct + ' aciertos'),
        el('div', 'podium-block', ['🥇', '🥈', '🥉'][idx]),
      );
      podium.appendChild(place);
    });
    if (!d.podium.length) podium.appendChild(el('p', 'waiting', 'No hubo jugadores.'));
    $('btn-report').href = d.game_id ? '/historial/' + d.game_id : '/historial';
    show('screen-final');
    Sound.fanfare();
    setTimeout(() => Confetti.burst(), 1600);
  });
  $('btn-again').onclick = () => location.reload();
  $('btn-continue').onclick = () => socket.emit('next');

  // -------------------------------------------------------------- reacciones
  socket.on('reaction', (d) => {
    const r = el('div', 'reaction', d.emoji);
    r.style.left = 4 + Math.random() * 88 + 'vw';
    document.body.appendChild(r);
    setTimeout(() => r.remove(), 3200);
  });

  // ----------------------------------------------------------- herramientas
  $('btn-sound').textContent = Sound.muted ? '🔇' : '🔊';
  $('btn-sound').onclick = () => { $('btn-sound').textContent = Sound.toggleMute() ? '🔇' : '🔊'; };
  $('btn-music').onclick = () => {
    Sound.music(!Sound.musicOn);
    $('btn-music').classList.toggle('on', Sound.musicOn);
  };
  $('btn-full').onclick = () => {
    if (document.fullscreenElement) document.exitFullscreen();
    else document.documentElement.requestFullscreen().catch(() => {});
  };
  $('btn-end').onclick = () => {
    if (confirm('¿Terminar la partida ahora y mostrar el podio?')) socket.emit('end_game');
  };

  // Espacio o Enter avanza a la siguiente pantalla.
  document.addEventListener('keydown', (e) => {
    if (e.key !== ' ' && e.key !== 'Enter') return;
    if (['INPUT', 'TEXTAREA', 'BUTTON'].includes(document.activeElement.tagName)) return;
    const btn = [...document.querySelectorAll('.screen:not([hidden]) .kbd-next')].find((b) => !b.disabled);
    if (btn) { e.preventDefault(); btn.click(); }
  });
})();
