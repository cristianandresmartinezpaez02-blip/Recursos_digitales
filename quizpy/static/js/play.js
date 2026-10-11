/* Pantalla del jugador (móvil). */
(() => {
  const socket = io();
  const $ = (id) => document.getElementById(id);
  const SHAPES = ['▲', '◆', '●', '■', '★', '⬢'];
  const AVATARS = ['🦊', '🐼', '🐸', '🐯', '🦄', '🐙', '🐵', '🐨', '🦁', '🐧', '🐢', '🦉',
    '🐳', '🐝', '🤖', '👽', '🐲', '🦖'];
  const REACTIONS = ['👍', '😂', '😮', '🔥', '❤️', '👏'];
  const STORE = 'quizpy-player';
  const NAME_STORE = 'quizpy-name';

  let me = load(STORE);
  let avatar = (me && me.avatar) || AVATARS[Math.floor(Math.random() * AVATARS.length)];
  let timeouts = [];
  let barTimer = null;
  let selected = new Set();
  let lastReaction = 0;

  function load(key) { try { return JSON.parse(localStorage.getItem(key)); } catch (e) { return null; } }
  function store(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* modo privado */ } }
  function forget() { me = null; try { localStorage.removeItem(STORE); } catch (e) { /* modo privado */ } }
  function later(fn, ms) { timeouts.push(setTimeout(fn, ms)); }
  function clearPending() { timeouts.forEach(clearTimeout); timeouts = []; clearInterval(barTimer); }
  function vibrate(p) { if (navigator.vibrate) navigator.vibrate(p); }

  function show(id, bg) {
    document.querySelectorAll('.p-screen').forEach((s) => { s.hidden = s.id !== id; });
    $('app').className = 'player-screen' + (bg ? ' ' + bg : '');
  }
  function wait(emoji, title, sub) {
    $('w-emoji').textContent = emoji;
    $('w-title').textContent = title;
    $('w-sub').textContent = sub || '';
    show('scr-wait');
  }

  // --------------------------------------------------------- formulario
  const urlPin = String(window.PIN || '').replace(/\D/g, '');
  $('in-pin').value = urlPin;
  $('in-name').value = load(NAME_STORE) || '';

  AVATARS.forEach((a) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = a;
    b.classList.toggle('sel', a === avatar);
    b.onclick = () => {
      avatar = a;
      document.querySelectorAll('#avatars button').forEach((x) => x.classList.toggle('sel', x === b));
    };
    $('avatars').appendChild(b);
  });

  $('join-form').onsubmit = (e) => {
    e.preventDefault();
    const pin = $('in-pin').value.replace(/\D/g, '');
    const name = $('in-name').value.trim();
    if (!pin || !name) return;
    $('join-error').textContent = '';
    $('btn-join').disabled = true;
    store(NAME_STORE, name);
    socket.emit('player_join', { pin, name, avatar });
  };

  const canRejoin = () => me && me.token && (!urlPin || urlPin === me.pin);
  if (canRejoin()) show('scr-connecting');
  else { forget(); show('scr-join'); }

  // ------------------------------------------------------------ conexión
  socket.on('connect', () => {
    $('offline').hidden = true;
    if (canRejoin()) socket.emit('player_join', me);
  });
  socket.on('disconnect', () => { $('offline').hidden = false; });

  socket.on('join_error', (d) => {
    $('btn-join').disabled = false;
    if (me) forget();
    show('scr-join');
    $('join-error').textContent = d.message;
  });

  socket.on('joined', (d) => {
    $('btn-join').disabled = false;
    me = { pin: d.pin, token: d.token, name: d.name, avatar: d.avatar };
    store(STORE, me);
    $('h-name').textContent = d.avatar + ' ' + d.name;
    $('h-score').textContent = d.score;
    history.replaceState(null, '', '/play?pin=' + d.pin);
    if (d.state === 'lobby') wait(d.avatar, '¡Ya estás dentro!', 'Busca tu nombre en la pantalla principal. La partida empezará pronto.');
    else if (d.state !== 'question' && d.state !== 'finished') wait('⏳', '¡Conectado!', 'Espera a la siguiente pregunta…');
  });

  socket.on('kicked', () => { clearPending(); forget(); show('scr-kicked'); });

  // ------------------------------------------------------------ pregunta
  socket.on('question', (q) => {
    clearPending();
    selected = new Set();
    const deadline = Date.now() + q.remaining * 1000;
    if (q.countdown > 0.3) {
      $('gr-label').textContent = `Pregunta ${q.index + 1} de ${q.total}`;
      $('gr-text').textContent = q.text;
      show('scr-ready');
      later(() => answerScreen(q, deadline), q.countdown * 1000);
    } else {
      answerScreen(q, deadline);
    }
  });

  function answerScreen(q, deadline) {
    $('a-counter').textContent = `${q.index + 1} / ${q.total}`;
    $('a-text').textContent = q.text;
    const img = $('a-image');
    img.hidden = !q.image;
    if (q.image) img.src = q.image; else img.removeAttribute('src');

    const box = $('a-options');
    box.innerHTML = '';
    box.className = 'p-answers' + (q.options.length === 2 ? ' two' : '');
    $('a-submit').hidden = q.type !== 'multi';
    $('a-submit').disabled = true;
    $('a-typed').hidden = q.type !== 'type';
    $('a-hint').textContent = q.type === 'multi' ? 'Marca todas las correctas y pulsa Enviar' : '';

    q.options.forEach((o, i) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'p-answer c' + i;
      const shape = document.createElement('span');
      shape.className = 'shape';
      shape.textContent = SHAPES[i];
      const label = document.createElement('span');
      label.textContent = o;
      b.append(shape, label);
      b.onclick = () => {
        if (q.type === 'multi') {
          if (selected.has(i)) selected.delete(i); else selected.add(i);
          b.classList.toggle('sel', selected.has(i));
          $('a-submit').disabled = selected.size === 0;
        } else {
          send(i);
        }
      };
      box.appendChild(b);
    });
    show('scr-answer');
    if (q.type === 'type') { $('a-input').value = ''; $('a-input').focus(); }

    const total = q.time * 1000;
    const update = () => {
      const left = Math.max(0, deadline - Date.now());
      $('a-timebar').style.width = (left / total) * 100 + '%';
      $('a-time').textContent = Math.ceil(left / 1000) + ' s';
      if (left <= 0) {
        clearInterval(barTimer);
        if (!$('scr-answer').hidden) wait('⏰', '¡Se acabó el tiempo!', 'Veamos los resultados…');
      }
    };
    update();
    barTimer = setInterval(update, 100);
  }

  function send(answer) {
    clearPending();
    socket.emit('answer', { answer });
    show('scr-sent');
    vibrate(30);
  }

  $('a-submit').onclick = () => { if (selected.size) send([...selected]); };
  $('a-typed').onsubmit = (e) => {
    e.preventDefault();
    const v = $('a-input').value.trim();
    if (v) send(v);
  };

  socket.on('answer_ack', () => {
    if ($('scr-sent').hidden) { clearPending(); show('scr-sent'); }
  });

  // ----------------------------------------------------------- resultado
  socket.on('your_result', (r) => {
    clearPending();
    $('h-score').textContent = r.score;
    let icon; let title; let bg;
    if (r.correct === null) {
      icon = '🗳️'; title = r.answered ? '¡Gracias por votar!' : 'Encuesta cerrada'; bg = 'bg-neutral';
    } else if (r.correct) {
      icon = '✅'; title = '¡Correcto!'; bg = 'bg-good';
      Sound.correct(); vibrate(80);
    } else {
      icon = r.answered ? '❌' : '⏰'; title = r.answered ? 'Incorrecto' : '¡Se acabó el tiempo!'; bg = 'bg-bad';
      Sound.wrong(); vibrate([60, 40, 60]);
    }
    $('r-icon').textContent = icon;
    $('r-title').textContent = title;
    $('r-points').textContent = '+' + r.points;
    $('r-points').hidden = !r.points;
    $('r-streak').textContent = r.streak >= 2 ? `🔥 ¡Racha de ${r.streak}!` : '';
    $('r-correct').textContent = !r.correct && r.correct_text ? 'Respuesta correcta: ' + r.correct_text : '';
    $('r-rank').textContent = `Vas en el puesto ${r.rank} de ${r.players}`;
    if (r.rank === 1) $('r-gap').textContent = '¡Vas en cabeza! 👑';
    else if (r.ahead) $('r-gap').textContent = `A ${r.gap} pts de ${r.ahead}`;
    else $('r-gap').textContent = '';
    show('scr-result', bg);
  });

  // ---------------------------------------------------------------- final
  socket.on('game_over', (r) => {
    clearPending();
    $('h-score').textContent = r.score;
    $('f-medal').textContent = ['🥇', '🥈', '🥉'][r.rank - 1] || '🎉';
    $('f-rank').textContent = `Puesto ${r.rank} de ${r.players}`;
    $('f-score').textContent = r.score + ' puntos';
    $('f-stats').textContent = `${r.correct} de ${r.questions} aciertos · mejor racha: ${r.best_streak}`;
    show('scr-final', r.rank <= 3 ? 'bg-gold' : '');
    if (r.rank <= 3) { Confetti.burst(); Sound.fanfare(); }
    forget();
  });

  // ----------------------------------------------------------- reacciones
  document.querySelectorAll('.reactions-bar').forEach((bar) => {
    REACTIONS.forEach((emoji) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'react';
      b.textContent = emoji;
      b.onclick = () => {
        if (Date.now() - lastReaction < 1000) return;
        lastReaction = Date.now();
        socket.emit('reaction', { emoji });
        b.classList.remove('pop');
        void b.offsetWidth;
        b.classList.add('pop');
      };
      bar.appendChild(b);
    });
  });
})();
