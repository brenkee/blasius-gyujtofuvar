-- User authentication schema
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL,
  email TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  last_login_at TEXT
);

INSERT INTO users (username, password_hash, role, email)
SELECT 'admin', '$2y$12$EYWtZ8ErGhR0LftRgSryA.7k056Y4DUewn4sYgBk22n5hfvqO98Cu', 'admin', ''
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');
