"""QuizPy: juego de preguntas en vivo estilo Kahoot (Flask + Socket.IO)."""
import csv
import io
import os
import random
import socket
import threading
import time
import unicodedata
import uuid
from collections import Counter

from flask import Flask, Response, abort, jsonify, render_template, request
from flask_socketio import SocketIO, emit, join_room, leave_room

import db

app = Flask(__name__)
app.config["SECRET_KEY"] = os.environ.get("SECRET_KEY") or uuid.uuid4().hex
app.json.ensure_ascii = False
socketio = SocketIO(app, async_mode="threading")

COUNTDOWN = 4          # segundos de "¡Prepárate!" antes de abrir respuestas
BASE_POINTS = 1000
STREAK_STEP = 100      # bonus por cada acierto consecutivo
STREAK_MAX = 500
GAME_TTL = 6 * 3600
MAX_PLAYERS = 300
REACTIONS = {"👍", "😂", "😮", "🔥", "❤️", "👏"}

games = {}       # pin -> Game
sessions = {}    # sid -> {"pin", "role", "player_id"}
lock = threading.RLock()

db.init()


# ------------------------------------------------------------------ modelo

def normalize(text):
    text = unicodedata.normalize("NFD", str(text))
    text = "".join(ch for ch in text if unicodedata.category(ch) != "Mn")
    return " ".join(text.lower().split())


class Player:
    def __init__(self, name, avatar, sid):
        self.id = uuid.uuid4().hex[:10]
        self.token = uuid.uuid4().hex
        self.name = name
        self.avatar = avatar
        self.sid = sid
        self.online = True
        self.score = 0
        self.streak = 0
        self.best_streak = 0
        self.correct = 0
        self.rank = 0
        self.prev_rank = 0
        self.last_reaction = 0.0
        self.answers = {}  # índice de pregunta -> resultado

    def public(self):
        return {
            "id": self.id,
            "name": self.name,
            "avatar": self.avatar,
            "score": self.score,
            "streak": self.streak,
            "online": self.online,
        }


class Game:
    def __init__(self, pin, quiz):
        self.pin = pin
        self.quiz_id = quiz["id"]
        self.title = quiz["title"]
        self.questions = list(quiz["questions"])
        self.host_key = uuid.uuid4().hex
        self.host_sid = None
        self.players = {}
        self.banned = set()
        self.state = "lobby"   # lobby | question | results | leaderboard | finished
        self.index = -1
        self.opens_at = 0.0
        self.token = None
        self.locked = False
        self.game_id = None
        self.created = time.time()

    @property
    def question(self):
        return self.questions[self.index]

    @property
    def is_last(self):
        return self.index >= len(self.questions) - 1

    def ranking(self):
        return sorted(self.players.values(), key=lambda p: (-p.score, p.name.lower()))

    def update_ranks(self):
        for pos, p in enumerate(self.ranking(), 1):
            p.prev_rank = p.rank or pos
            p.rank = pos

    def answered_count(self):
        return sum(1 for p in self.players.values() if self.index in p.answers)


def to_host(game, event, data):
    socketio.emit(event, data, to=f"{game.pin}:host")


def to_players(game, event, data):
    socketio.emit(event, data, to=game.pin)


def to_player(player, event, data):
    if player.sid and player.online:
        socketio.emit(event, data, to=player.sid)


def send_player_list(game):
    to_host(game, "player_list", {
        "players": [p.public() for p in game.players.values()],
        "locked": game.locked,
    })


def new_pin():
    while True:
        pin = f"{random.randint(100000, 999999)}"
        if pin not in games:
            return pin


def cleanup():
    limit = time.time() - GAME_TTL
    for pin in [pin for pin, g in games.items() if g.created < limit]:
        del games[pin]


# ---------------------------------------------------------------- preguntas

def public_question(game):
    q = game.question
    now = time.time()
    return {
        "index": game.index,
        "total": len(game.questions),
        "type": q["type"],
        "text": q["text"],
        "image": q.get("image", ""),
        "options": q.get("options", []),
        "time": q["time"],
        "points": q["points"],
        "countdown": max(0.0, game.opens_at - now),
        "remaining": max(0.0, game.opens_at + q["time"] - now),
    }


def sanitize_answer(q, answer):
    n = len(q.get("options", []))
    if q["type"] in ("quiz", "tf", "poll"):
        a = int(answer)
        if not 0 <= a < n:
            raise ValueError
        return a
    if q["type"] == "multi":
        a = sorted({int(x) for x in answer})
        if not a or any(not 0 <= x < n for x in a):
            raise ValueError
        return a
    a = " ".join(str(answer).split())[:80]
    if not a:
        raise ValueError
    return a


def grade(q, answer):
    """True/False según acierto; None para encuestas."""
    if q["type"] == "poll":
        return None
    if q["type"] in ("quiz", "tf"):
        return answer in q["correct"]
    if q["type"] == "multi":
        return answer == sorted(q["correct"])
    return normalize(answer) in {normalize(a) for a in q["accepted"]}


def answer_label(q, answer):
    if answer is None:
        return ""
    if q["type"] == "type":
        return answer
    if q["type"] == "multi":
        return ", ".join(q["options"][i] for i in answer)
    return q["options"][answer]


def correct_label(q):
    if q["type"] == "type":
        return " / ".join(q["accepted"])
    return ", ".join(q["options"][i] for i in q["correct"])


def award(game, player, answer):
    q = game.question
    elapsed = max(0.0, time.time() - game.opens_at)
    ok = grade(q, answer)
    points = 0
    if ok:
        player.streak += 1
        player.best_streak = max(player.best_streak, player.streak)
        player.correct += 1
        if q["points"]:
            speed = 1 - min(elapsed / q["time"], 1) / 2       # de 100 % a 50 %
            points = round(BASE_POINTS * q["points"] * speed)
            points += min((player.streak - 1) * STREAK_STEP, STREAK_MAX)
    elif ok is False:
        player.streak = 0
    player.score += points
    player.answers[game.index] = {
        "answer": answer,
        "text": answer_label(q, answer),
        "correct": ok,
        "points": points,
        "time": round(elapsed, 2),
    }


def start_question(game):
    game.index += 1
    if game.index >= len(game.questions):
        finish_game(game)
        return
    game.state = "question"
    game.opens_at = time.time() + COUNTDOWN
    game.token = token = uuid.uuid4().hex
    data = public_question(game)
    to_host(game, "question", data)
    to_players(game, "question", data)
    socketio.start_background_task(question_timer, game, token, COUNTDOWN + game.question["time"])


def question_timer(game, token, delay):
    socketio.sleep(delay + 0.6)   # pequeño margen para la latencia de red
    with lock:
        if game.token == token and game.state == "question":
            end_question(game)


def maybe_end_question(game):
    if game.state != "question":
        return
    pending = [p for p in game.players.values() if p.online and game.index not in p.answers]
    if not pending and game.answered_count():
        end_question(game)


def end_question(game):
    game.state = "results"
    game.token = None
    q = game.question
    for p in game.players.values():
        if game.index not in p.answers:
            if q["type"] != "poll":
                p.streak = 0
            p.answers[game.index] = {"answer": None, "text": "", "correct": None if q["type"] == "poll" else False,
                                     "points": 0, "time": None}
    game.update_ranks()

    results = [p.answers[game.index] for p in game.players.values()]
    given = [r["answer"] for r in results if r["answer"] is not None]
    if q["type"] == "type":
        accepted = {normalize(a) for a in q["accepted"]}
        raw = {}
        for a in given:
            raw.setdefault(normalize(a), a)
        counts = Counter(normalize(a) for a in given)
        distribution = [{"label": raw[k], "count": v, "correct": k in accepted} for k, v in counts.most_common(6)]
    else:
        counts = [0] * len(q["options"])
        for a in given:
            for i in (a if isinstance(a, list) else [a]):
                counts[i] += 1
        distribution = [{"label": opt, "count": counts[i], "correct": i in q["correct"]}
                        for i, opt in enumerate(q["options"])]

    to_host(game, "question_end", {
        "index": game.index,
        "type": q["type"],
        "distribution": distribution,
        "answered": len(given),
        "total": len(game.players),
        "correct_count": sum(1 for r in results if r["correct"]),
        "correct_text": "" if q["type"] == "poll" else correct_label(q),
        "is_last": game.is_last,
    })

    ranking = game.ranking()
    for pos, p in enumerate(ranking):
        r = p.answers[game.index]
        ahead = ranking[pos - 1] if pos > 0 else None
        to_player(p, "your_result", {
            "correct": r["correct"],
            "answered": r["answer"] is not None,
            "points": r["points"],
            "score": p.score,
            "streak": p.streak,
            "rank": p.rank,
            "players": len(ranking),
            "ahead": ahead.name if ahead else None,
            "gap": ahead.score - p.score if ahead else 0,
            "correct_text": "" if q["type"] == "poll" else correct_label(q),
        })


def show_leaderboard(game):
    game.state = "leaderboard"
    top = [dict(p.public(), rank=p.rank, delta=p.prev_rank - p.rank) for p in game.ranking()[:5]]
    to_host(game, "leaderboard", {"top": top, "index": game.index, "total": len(game.questions)})


def finish_game(game):
    game.state = "finished"
    game.token = None
    game.update_ranks()
    ranking = game.ranking()
    played = max(0, min(game.index + 1, len(game.questions)))
    if ranking and played:
        report = {
            "questions": [{"text": q["text"], "type": q["type"], "correct": "" if q["type"] == "poll" else correct_label(q)}
                          for q in game.questions[:played]],
            "players": [{
                "rank": p.rank, "name": p.name, "avatar": p.avatar, "score": p.score,
                "correct": p.correct, "best_streak": p.best_streak,
                "answers": [p.answers.get(i) for i in range(played)],
            } for p in ranking],
        }
        game.game_id = db.save_game(game.quiz_id, game.title, game.pin, report)

    to_host(game, "game_over", {
        "title": game.title,
        "podium": [dict(p.public(), rank=p.rank, correct=p.correct) for p in ranking[:5]],
        "game_id": game.game_id,
    })
    for p in ranking:
        to_player(p, "game_over", personal_summary(game, p))


def personal_summary(game, p):
    return {
        "rank": p.rank,
        "players": len(game.players),
        "score": p.score,
        "correct": p.correct,
        "questions": max(0, min(game.index + 1, len(game.questions))),
        "best_streak": p.best_streak,
    }


# ------------------------------------------------------------ eventos socket

def current_host_game():
    s = sessions.get(request.sid)
    if not s or s["role"] != "host":
        return None
    game = games.get(s["pin"])
    return game if game and game.host_sid == request.sid else None


def current_player():
    s = sessions.get(request.sid)
    if not s or s["role"] != "player":
        return None, None
    game = games.get(s["pin"])
    player = game.players.get(s["player_id"]) if game else None
    return (game, player) if player else (None, None)


def attach_host(game):
    if game.host_sid and game.host_sid != request.sid:
        sessions.pop(game.host_sid, None)
    game.host_sid = request.sid
    sessions[request.sid] = {"pin": game.pin, "role": "host"}
    join_room(f"{game.pin}:host")
    emit("game_created", {"pin": game.pin, "host_key": game.host_key, "title": game.title,
                          "total": len(game.questions), "state": game.state})
    send_player_list(game)


@socketio.on("host_create")
def on_host_create(data):
    try:
        quiz = db.get_quiz(int((data or {}).get("quiz_id")))
    except (TypeError, ValueError):
        quiz = None
    if not quiz or not quiz["questions"]:
        emit("error_msg", {"message": "Ese quiz no existe o no tiene preguntas."})
        return
    with lock:
        cleanup()
        game = Game(new_pin(), quiz)
        games[game.pin] = game
        attach_host(game)


@socketio.on("host_rejoin")
def on_host_rejoin(data):
    data = data or {}
    with lock:
        game = games.get(str(data.get("pin", "")))
        if not game or game.host_key != data.get("host_key") or game.state == "finished":
            emit("rejoin_failed")
            return
        attach_host(game)


@socketio.on("player_join")
def on_player_join(data):
    data = data or {}
    pin = "".join(ch for ch in str(data.get("pin", "")) if ch.isdigit())
    with lock:
        game = games.get(pin)
        if not game:
            emit("join_error", {"message": "No hay ninguna partida con ese PIN."})
            return
        token = data.get("token")
        player = next((p for p in game.players.values() if token and p.token == token), None)
        if player:
            if player.sid and player.sid != request.sid:
                sessions.pop(player.sid, None)
        else:
            name = " ".join(str(data.get("name", "")).split())[:20]
            if game.state == "finished":
                emit("join_error", {"message": "Esta partida ya terminó."})
                return
            if game.locked:
                emit("join_error", {"message": "El anfitrión ha bloqueado la entrada."})
                return
            if not name:
                emit("join_error", {"message": "Escribe un apodo."})
                return
            if name.lower() in game.banned:
                emit("join_error", {"message": "No puedes volver a entrar a esta partida."})
                return
            if any(p.name.lower() == name.lower() for p in game.players.values()):
                emit("join_error", {"message": "Ese apodo ya está en uso. Prueba con otro."})
                return
            if len(game.players) >= MAX_PLAYERS:
                emit("join_error", {"message": "La partida está llena."})
                return
            avatar = str(data.get("avatar") or "🙂")[:8]
            player = Player(name, avatar, request.sid)
            game.players[player.id] = player

        player.sid = request.sid
        player.online = True
        sessions[request.sid] = {"pin": pin, "role": "player", "player_id": player.id}
        join_room(pin)
        emit("joined", {"pin": pin, "token": player.token, "name": player.name, "avatar": player.avatar,
                        "score": player.score, "title": game.title, "state": game.state})
        if game.state == "question":
            if game.index in player.answers:
                emit("answer_ack", {})
            else:
                emit("question", public_question(game))
        elif game.state == "finished":
            emit("game_over", personal_summary(game, player))
        send_player_list(game)


@socketio.on("answer")
def on_answer(data):
    with lock:
        game, player = current_player()
        if not player or game.state != "question" or game.index in player.answers:
            return
        now = time.time()
        if now < game.opens_at - 0.3 or now > game.opens_at + game.question["time"] + 1.0:
            return
        try:
            answer = sanitize_answer(game.question, (data or {}).get("answer"))
        except (TypeError, ValueError):
            return
        award(game, player, answer)
        emit("answer_ack", {})
        to_host(game, "answer_count", {"count": game.answered_count(), "total": len(game.players)})
        maybe_end_question(game)


@socketio.on("next")
def on_next(data=None):
    with lock:
        game = current_host_game()
        if not game:
            return
        if game.state == "lobby":
            if not game.players:
                emit("error_msg", {"message": "Espera a que se una al menos un jugador."})
                return
            if (data or {}).get("shuffle"):
                random.shuffle(game.questions)
            start_question(game)
        elif game.state == "question":
            end_question(game)
        elif game.state == "results":
            if game.is_last:
                finish_game(game)
            else:
                show_leaderboard(game)
        elif game.state == "leaderboard":
            start_question(game)


@socketio.on("end_game")
def on_end_game():
    with lock:
        game = current_host_game()
        if game and game.state != "finished":
            if game.state == "question":
                end_question(game)
            finish_game(game)


@socketio.on("lock")
def on_lock(data):
    with lock:
        game = current_host_game()
        if game:
            game.locked = bool((data or {}).get("locked"))
            send_player_list(game)


@socketio.on("kick")
def on_kick(data):
    with lock:
        game = current_host_game()
        if not game:
            return
        player = game.players.pop(str((data or {}).get("player_id")), None)
        if not player:
            return
        game.banned.add(player.name.lower())
        if player.sid:
            socketio.emit("kicked", {}, to=player.sid)
            leave_room(game.pin, sid=player.sid)
            sessions.pop(player.sid, None)
        send_player_list(game)
        maybe_end_question(game)


@socketio.on("reaction")
def on_reaction(data):
    emoji = str((data or {}).get("emoji", ""))
    if emoji not in REACTIONS:
        return
    with lock:
        game, player = current_player()
        if not player or time.time() - player.last_reaction < 1.0:
            return
        player.last_reaction = time.time()
        to_host(game, "reaction", {"emoji": emoji, "name": player.name})


@socketio.on("disconnect")
def on_disconnect(*_args):
    with lock:
        s = sessions.pop(request.sid, None)
        game = games.get(s["pin"]) if s else None
        if not game:
            return
        if s["role"] == "host":
            if game.host_sid == request.sid:
                game.host_sid = None
            return
        player = game.players.get(s["player_id"])
        if player and player.sid == request.sid:
            player.online = False
            send_player_list(game)
            maybe_end_question(game)


# ------------------------------------------------------------------- páginas

def lan_ip():
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("10.255.255.255", 1))
        return s.getsockname()[0]
    except OSError:
        return "127.0.0.1"
    finally:
        s.close()


def join_base():
    """URL que verán los jugadores: si el anfitrión usa localhost, damos la IP de la red local."""
    host = request.host
    hostname, _, port = host.partition(":")
    if hostname in ("localhost", "127.0.0.1"):
        host = lan_ip() + (f":{port}" if port else "")
    return f"{request.scheme}://{host}/"


@app.route("/")
def index():
    return render_template("index.html")


@app.route("/play")
def play():
    return render_template("play.html", pin=request.args.get("pin", ""))


@app.route("/quizzes")
def quizzes():
    return render_template("quizzes.html", quizzes=db.list_quizzes())


@app.route("/editor", defaults={"quiz_id": None})
@app.route("/editor/<int:quiz_id>")
def editor(quiz_id):
    quiz = db.get_quiz(quiz_id) if quiz_id else None
    if quiz_id and not quiz:
        abort(404)
    return render_template("editor.html", quiz=quiz)


@app.route("/host/<int:quiz_id>")
def host(quiz_id):
    quiz = db.get_quiz(quiz_id)
    if not quiz:
        abort(404)
    return render_template("host.html", quiz=quiz, join_base=join_base())


@app.route("/historial")
def history():
    return render_template("historial.html", games=db.list_games())


def question_stats(report):
    players = report["players"]
    stats = []
    for i, q in enumerate(report["questions"]):
        res = [p["answers"][i] for p in players if i < len(p["answers"]) and p["answers"][i]]
        times = [r["time"] for r in res if r.get("answer") is not None and r.get("time") is not None]
        stats.append({
            **q,
            "answered": len(times),
            "rate": None if q["type"] == "poll" or not players
            else round(100 * sum(1 for r in res if r.get("correct")) / len(players)),
            "avg_time": round(sum(times) / len(times), 1) if times else None,
        })
    return stats


@app.route("/historial/<int:game_id>")
def game_detail(game_id):
    game = db.get_game(game_id)
    if not game:
        abort(404)
    return render_template("partida.html", game=game, stats=question_stats(game["report"]))


@app.route("/historial/<int:game_id>.csv")
def game_csv(game_id):
    game = db.get_game(game_id)
    if not game:
        abort(404)
    report = game["report"]
    out = io.StringIO()
    w = csv.writer(out, delimiter=";")
    header = ["Puesto", "Jugador", "Puntos", "Aciertos", "Mejor racha"]
    for i in range(len(report["questions"])):
        header += [f"P{i + 1} respuesta", f"P{i + 1} puntos", f"P{i + 1} segundos"]
    w.writerow(header)
    for p in report["players"]:
        row = [p["rank"], p["name"], p["score"], p["correct"], p["best_streak"]]
        for a in p["answers"]:
            a = a or {}
            row += [a.get("text", ""), a.get("points", 0), a.get("time") if a.get("time") is not None else ""]
        w.writerow(row)
    filename = f"partida_{game_id}.csv"
    return Response("﻿" + out.getvalue(), mimetype="text/csv",
                    headers={"Content-Disposition": f"attachment; filename={filename}"})


# ---------------------------------------------------------------------- API

def api_error(message, status=400):
    return jsonify({"error": message}), status


@app.get("/api/quizzes/<int:quiz_id>")
def api_get_quiz(quiz_id):
    quiz = db.get_quiz(quiz_id)
    return jsonify(quiz) if quiz else api_error("No encontrado", 404)


@app.post("/api/quizzes")
def api_create_quiz():
    try:
        return jsonify({"id": db.save_quiz(request.get_json(silent=True))}), 201
    except db.QuizError as e:
        return api_error(str(e))


@app.put("/api/quizzes/<int:quiz_id>")
def api_update_quiz(quiz_id):
    try:
        saved = db.save_quiz(request.get_json(silent=True), quiz_id)
    except db.QuizError as e:
        return api_error(str(e))
    return jsonify({"id": saved}) if saved else api_error("No encontrado", 404)


@app.post("/api/quizzes/<int:quiz_id>/duplicate")
def api_duplicate_quiz(quiz_id):
    quiz = db.get_quiz(quiz_id)
    if not quiz:
        return api_error("No encontrado", 404)
    quiz["title"] = (quiz["title"] + " (copia)")[:120]
    return jsonify({"id": db.save_quiz(quiz)}), 201


@app.delete("/api/quizzes/<int:quiz_id>")
def api_delete_quiz(quiz_id):
    return jsonify({"ok": True}) if db.delete_quiz(quiz_id) else api_error("No encontrado", 404)


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    print("\n  QuizPy esta listo")
    print(f"  En este equipo:        http://localhost:{port}")
    print(f"  Desde móviles (wifi):  http://{lan_ip()}:{port}\n")
    socketio.run(app, host="0.0.0.0", port=port, allow_unsafe_werkzeug=True)
