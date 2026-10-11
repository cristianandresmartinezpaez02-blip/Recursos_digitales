/* Editor de quizzes. */
(() => {
  const $ = (id) => document.getElementById(id);
  const SHAPES = ['▲', '◆', '●', '■', '★', '⬢'];
  const TYPES = {
    quiz: 'Quiz', multi: 'Varias correctas', tf: 'Verdadero / Falso', type: 'Escribir respuesta', poll: 'Encuesta',
  };
  const TIMES = [5, 10, 15, 20, 30, 45, 60, 90, 120, 180, 240];
  const HINTS = {
    quiz: 'Marca con ✔ la respuesta correcta. Si marcas varias, cualquiera de ellas será válida.',
    multi: 'Marca todas las correctas: el jugador tiene que seleccionarlas todas para acertar.',
    tf: 'Marca cuál es la correcta.',
    poll: 'Las encuestas no dan puntos: sirven para conocer la opinión del grupo.',
  };

  const blank = (type = 'quiz') => ({
    type,
    text: '',
    image: '',
    time: 20,
    points: type === 'poll' ? 0 : 1,
    options: type === 'tf' ? ['Verdadero', 'Falso'] : type === 'type' ? [] : ['', '', '', ''],
    correct: [],
    accepted: [],
  });

  let quizId = window.QUIZ ? window.QUIZ.id : null;
  const quiz = window.QUIZ
    ? JSON.parse(JSON.stringify(window.QUIZ))
    : { title: '', description: '', questions: [blank()] };
  quiz.questions.forEach((q) => {
    q.options = q.options || [];
    q.correct = q.correct || [];
    q.accepted = q.accepted || [];
  });
  let dirty = false;

  const list = $('questions');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
  const opt = (value, label, selected) => `<option value="${value}"${selected ? ' selected' : ''}>${label}</option>`;

  function card(q, i) {
    const types = Object.entries(TYPES).map(([k, v]) => opt(k, v, k === q.type)).join('');
    const times = [...new Set([...TIMES, q.time])].sort((a, b) => a - b)
      .map((t) => opt(t, `⏱ ${t} s`, t === q.time)).join('');
    const points = q.type === 'poll'
      ? opt(0, 'Sin puntos', true)
      : [[1, 'Puntos normales'], [2, '⚡ Puntos dobles'], [0, 'Sin puntos']].map(([v, l]) => opt(v, l, v === q.points)).join('');

    let body;
    if (q.type === 'type') {
      body = `<label class="lbl">Respuestas aceptadas, una por línea (no importan mayúsculas ni tildes)</label>
        <textarea class="field" data-f="accepted" rows="3" placeholder="París&#10;Paris">${esc(q.accepted.join('\n'))}</textarea>`;
    } else {
      body = q.options.map((o, j) => `
        <div class="opt-row">
          <span class="shape-badge c${j}">${SHAPES[j]}</span>
          <input class="field" type="text" data-opt="${j}" value="${esc(o)}" maxlength="120"
                 placeholder="Respuesta ${j + 1}" ${q.type === 'tf' ? 'readonly' : ''}>
          ${q.type !== 'poll' ? `<button type="button" class="correct-toggle${q.correct.includes(j) ? ' on' : ''}" data-act="correct" data-j="${j}" title="Marcar como correcta">✔</button>` : ''}
          ${q.type !== 'tf' && q.options.length > 2 ? `<button type="button" class="icon-btn" data-act="rmopt" data-j="${j}" title="Quitar respuesta">✖</button>` : ''}
        </div>`).join('');
      if (q.type !== 'tf' && q.options.length < 6) {
        body += '<button type="button" class="btn btn-light btn-sm" data-act="addopt">+ Añadir respuesta</button>';
      }
      body += `<p class="hint">${HINTS[q.type]}</p>`;
    }

    return `<article class="card q-card type-${q.type}" data-i="${i}">
      <div class="q-head">
        <span class="q-num">${i + 1}</span>
        <select class="field sel" data-f="type">${types}</select>
        <select class="field sel" data-f="time">${times}</select>
        <select class="field sel" data-f="points">${points}</select>
        <div class="q-tools">
          <button type="button" class="icon-btn" data-act="up" title="Subir">↑</button>
          <button type="button" class="icon-btn" data-act="down" title="Bajar">↓</button>
          <button type="button" class="icon-btn" data-act="dup" title="Duplicar">⧉</button>
          <button type="button" class="icon-btn danger" data-act="del" title="Eliminar">🗑</button>
        </div>
      </div>
      <textarea class="field q-input" data-f="text" rows="2" maxlength="300" placeholder="Escribe la pregunta…">${esc(q.text)}</textarea>
      <input class="field" type="url" data-f="image" value="${esc(q.image)}" placeholder="URL de una imagen (opcional)">
      <div class="opts">${body}</div>
    </article>`;
  }

  function render() {
    list.innerHTML = quiz.questions.map(card).join('');
    $('q-total').textContent = quiz.questions.length;
  }

  function changeType(q, type) {
    const old = q.type;
    q.type = type;
    if (type === 'tf') {
      q.options = ['Verdadero', 'Falso'];
      q.correct = q.correct.filter((x) => x < 2).slice(0, 1);
    } else if (type === 'type') {
      if (!q.accepted.length) q.accepted = q.options.filter((o, j) => q.correct.includes(j) && o.trim());
      q.options = [];
      q.correct = [];
    } else if (old === 'tf' || old === 'type' || q.options.length < 2) {
      q.options = ['', '', '', ''];
      q.correct = [];
    }
    if (type === 'poll') { q.correct = []; q.points = 0; } else if (old === 'poll') q.points = 1;
  }

  // ------------------------------------------------------------- eventos
  list.addEventListener('input', (e) => {
    const t = e.target;
    const cardEl = t.closest('.q-card');
    if (!cardEl) return;
    const q = quiz.questions[+cardEl.dataset.i];
    dirty = true;
    if (t.dataset.opt !== undefined) q.options[+t.dataset.opt] = t.value;
    else if (t.dataset.f === 'text' || t.dataset.f === 'image') q[t.dataset.f] = t.value;
    else if (t.dataset.f === 'accepted') q.accepted = t.value.split('\n').map((s) => s.trim()).filter(Boolean);
  });

  list.addEventListener('change', (e) => {
    const t = e.target;
    if (t.tagName !== 'SELECT') return;
    const q = quiz.questions[+t.closest('.q-card').dataset.i];
    dirty = true;
    if (t.dataset.f === 'type') { changeType(q, t.value); render(); }
    else if (t.dataset.f === 'time') q.time = +t.value;
    else if (t.dataset.f === 'points') q.points = +t.value;
  });

  list.addEventListener('click', (e) => {
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const i = +b.closest('.q-card').dataset.i;
    const qs = quiz.questions;
    const q = qs[i];
    const j = +b.dataset.j;
    switch (b.dataset.act) {
      case 'correct':
        if (q.type === 'tf') q.correct = [j];
        else q.correct = q.correct.includes(j) ? q.correct.filter((x) => x !== j) : [...q.correct, j].sort();
        break;
      case 'addopt': q.options.push(''); break;
      case 'rmopt':
        q.options.splice(j, 1);
        q.correct = q.correct.filter((x) => x !== j).map((x) => (x > j ? x - 1 : x));
        break;
      case 'up': if (i > 0) [qs[i - 1], qs[i]] = [qs[i], qs[i - 1]]; break;
      case 'down': if (i < qs.length - 1) [qs[i + 1], qs[i]] = [qs[i], qs[i + 1]]; break;
      case 'dup': qs.splice(i + 1, 0, JSON.parse(JSON.stringify(q))); break;
      case 'del':
        if (qs.length === 1) { toast('El quiz necesita al menos una pregunta.', true); return; }
        if (!confirm('¿Eliminar esta pregunta?')) return;
        qs.splice(i, 1);
        break;
      default: return;
    }
    dirty = true;
    render();
  });

  document.querySelectorAll('[data-add]').forEach((b) => {
    b.onclick = () => {
      quiz.questions.push(blank(b.dataset.add));
      dirty = true;
      render();
      list.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' });
      list.lastElementChild.querySelector('[data-f="text"]').focus({ preventScroll: true });
    };
  });

  $('quiz-title').value = quiz.title;
  $('quiz-desc').value = quiz.description;
  $('quiz-title').oninput = (e) => { quiz.title = e.target.value; dirty = true; };
  $('quiz-desc').oninput = (e) => { quiz.description = e.target.value; dirty = true; };

  // ------------------------------------------------------------- guardar
  let toastTimer = null;
  function toast(msg, isError) {
    const t = $('toast');
    t.textContent = msg;
    t.className = 'toast' + (isError ? ' error' : '');
    t.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.hidden = true; }, 3500);
  }

  async function save(thenPlay) {
    const res = await fetch(quizId ? `/api/quizzes/${quizId}` : '/api/quizzes', {
      method: quizId ? 'PUT' : 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(quiz),
    }).catch(() => null);
    if (!res) { toast('No se pudo conectar con el servidor.', true); return; }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { toast(data.error || 'No se pudo guardar.', true); return; }
    quizId = data.id;
    dirty = false;
    history.replaceState(null, '', `/editor/${quizId}`);
    if (thenPlay) location.href = `/host/${quizId}`;
    else toast('¡Guardado! ✔');
  }

  $('btn-save').onclick = () => save(false);
  $('btn-save-play').onclick = () => save(true);
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(false); }
  });
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  render();
})();
