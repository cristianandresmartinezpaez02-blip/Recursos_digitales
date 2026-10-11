"""Persistencia en SQLite: quizzes y resultados de partidas."""
import json
import os
import sqlite3
import time
from contextlib import contextmanager

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "quizpy.db")
TYPES = ("quiz", "multi", "tf", "type", "poll")


class QuizError(ValueError):
    """Error de validación con un mensaje apto para mostrar al usuario."""


@contextmanager
def connect():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    try:
        yield conn
        conn.commit()
    finally:
        conn.close()


def fmt_date(ts):
    return time.strftime("%d/%m/%Y %H:%M", time.localtime(ts))


def init():
    with connect() as c:
        c.executescript(
            """
            CREATE TABLE IF NOT EXISTS quizzes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                questions TEXT NOT NULL,
                created_at REAL NOT NULL,
                updated_at REAL NOT NULL
            );
            CREATE TABLE IF NOT EXISTS games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                quiz_id INTEGER,
                title TEXT NOT NULL,
                pin TEXT NOT NULL,
                played_at REAL NOT NULL,
                players INTEGER NOT NULL,
                report TEXT NOT NULL
            );
            """
        )
        empty = c.execute("SELECT 1 FROM quizzes LIMIT 1").fetchone() is None
    if empty:
        save_quiz(SAMPLE_QUIZ)


# ---------------------------------------------------------------- validación

def _clean_question(q, n):
    if not isinstance(q, dict):
        raise QuizError(f"Pregunta {n}: formato no válido.")
    qtype = q.get("type", "quiz")
    if qtype not in TYPES:
        raise QuizError(f"Pregunta {n}: tipo desconocido.")
    text = str(q.get("text", "")).strip()[:300]
    if not text:
        raise QuizError(f"Pregunta {n}: escribe el enunciado.")
    image = str(q.get("image", "") or "").strip()[:500]
    if not image.startswith(("http://", "https://")):
        image = ""
    points = int(q.get("points", 1))
    if points not in (0, 1, 2) or qtype == "poll":
        points = 0 if qtype == "poll" else 1
    out = {
        "type": qtype,
        "text": text,
        "image": image,
        "time": max(5, min(240, int(q.get("time", 20)))),
        "points": points,
        "options": [],
        "correct": [],
        "accepted": [],
    }

    if qtype == "type":
        accepted = [str(a).strip()[:80] for a in q.get("accepted", []) if str(a).strip()]
        if not accepted:
            raise QuizError(f"Pregunta {n}: indica al menos una respuesta aceptada.")
        out["accepted"] = accepted[:10]
        return out

    if qtype == "tf":
        options = ["Verdadero", "Falso"]
    else:
        options = [str(o).strip()[:120] for o in q.get("options", [])]
        if not 2 <= len(options) <= 6:
            raise QuizError(f"Pregunta {n}: debe tener entre 2 y 6 respuestas.")
        if any(not o for o in options):
            raise QuizError(f"Pregunta {n}: hay una respuesta vacía.")
    correct = sorted({int(i) for i in q.get("correct", []) if 0 <= int(i) < len(options)})
    if qtype == "poll":
        correct = []
    elif not correct:
        raise QuizError(f"Pregunta {n}: marca la respuesta correcta.")
    elif qtype == "tf":
        correct = correct[:1]
    out["options"] = options
    out["correct"] = correct
    return out


def validate_quiz(data):
    if not isinstance(data, dict):
        raise QuizError("Formato de quiz no válido.")
    title = str(data.get("title", "")).strip()[:120]
    if not title:
        raise QuizError("El quiz necesita un título.")
    raw = data.get("questions")
    if not isinstance(raw, list) or not raw:
        raise QuizError("Agrega al menos una pregunta.")
    if len(raw) > 100:
        raise QuizError("Máximo 100 preguntas por quiz.")
    questions = []
    for n, q in enumerate(raw, 1):
        try:
            questions.append(_clean_question(q, n))
        except QuizError:
            raise
        except (TypeError, ValueError):
            raise QuizError(f"Pregunta {n}: datos no válidos.")
    return {
        "title": title,
        "description": str(data.get("description", "") or "").strip()[:500],
        "questions": questions,
    }


# ------------------------------------------------------------------ quizzes

def list_quizzes():
    with connect() as c:
        rows = c.execute("SELECT * FROM quizzes ORDER BY updated_at DESC").fetchall()
    return [
        {
            "id": r["id"],
            "title": r["title"],
            "description": r["description"],
            "count": len(json.loads(r["questions"])),
            "updated": fmt_date(r["updated_at"]),
        }
        for r in rows
    ]


def get_quiz(quiz_id):
    with connect() as c:
        r = c.execute("SELECT * FROM quizzes WHERE id = ?", (quiz_id,)).fetchone()
    if not r:
        return None
    return {
        "id": r["id"],
        "title": r["title"],
        "description": r["description"],
        "questions": json.loads(r["questions"]),
    }


def save_quiz(data, quiz_id=None):
    quiz = validate_quiz(data)
    now = time.time()
    payload = json.dumps(quiz["questions"], ensure_ascii=False)
    with connect() as c:
        if quiz_id is None:
            cur = c.execute(
                "INSERT INTO quizzes (title, description, questions, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
                (quiz["title"], quiz["description"], payload, now, now),
            )
            return cur.lastrowid
        cur = c.execute(
            "UPDATE quizzes SET title = ?, description = ?, questions = ?, updated_at = ? WHERE id = ?",
            (quiz["title"], quiz["description"], payload, now, quiz_id),
        )
        return quiz_id if cur.rowcount else None


def delete_quiz(quiz_id):
    with connect() as c:
        return c.execute("DELETE FROM quizzes WHERE id = ?", (quiz_id,)).rowcount > 0


# ------------------------------------------------------------------ partidas

def save_game(quiz_id, title, pin, report):
    with connect() as c:
        cur = c.execute(
            "INSERT INTO games (quiz_id, title, pin, played_at, players, report) VALUES (?, ?, ?, ?, ?, ?)",
            (quiz_id, title, pin, time.time(), len(report["players"]), json.dumps(report, ensure_ascii=False)),
        )
        return cur.lastrowid


def list_games():
    with connect() as c:
        rows = c.execute("SELECT id, title, pin, played_at, players FROM games ORDER BY played_at DESC LIMIT 200").fetchall()
    return [dict(r, played=fmt_date(r["played_at"])) for r in rows]


def get_game(game_id):
    with connect() as c:
        r = c.execute("SELECT * FROM games WHERE id = ?", (game_id,)).fetchone()
    if not r:
        return None
    return dict(r, played=fmt_date(r["played_at"]), report=json.loads(r["report"]))


SAMPLE_QUIZ = {
    "title": "Cultura general (ejemplo)",
    "description": "Un quiz de muestra con todos los tipos de pregunta.",
    "questions": [
        {"type": "quiz", "text": "¿Cuál es el planeta más grande del sistema solar?", "time": 20, "points": 1,
         "options": ["Saturno", "Júpiter", "Neptuno", "La Tierra"], "correct": [1]},
        {"type": "tf", "text": "Python debe su nombre a los humoristas Monty Python.", "time": 15, "points": 1,
         "correct": [0]},
        {"type": "multi", "text": "¿Cuáles de estos son lenguajes de programación?", "time": 30, "points": 1,
         "options": ["Python", "HTML", "JavaScript", "CSS"], "correct": [0, 2]},
        {"type": "type", "text": "¿Cuál es la capital de Colombia?", "time": 25, "points": 2,
         "accepted": ["Bogotá", "Bogota D.C.", "Santa Fe de Bogotá"]},
        {"type": "quiz", "text": "¿Cuántos lados tiene un hexágono?", "time": 15, "points": 1,
         "options": ["5", "6", "7", "8"], "correct": [1]},
        {"type": "poll", "text": "¿Qué te ha parecido este quiz?", "time": 20, "points": 0,
         "options": ["¡Genial!", "Bien", "Regular", "Aburrido"]},
    ],
}
