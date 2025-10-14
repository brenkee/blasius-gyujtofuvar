-- SQLite schema for the Gyűjtőfuvar application
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration TEXT PRIMARY KEY,
  executed_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS items (
  id TEXT PRIMARY KEY,
  position INTEGER NOT NULL,
  data TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS round_meta (
  round_id TEXT PRIMARY KEY,
  data TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_items_position ON items(position);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  email TEXT,
  role TEXT NOT NULL DEFAULT 'editor',
  password_hash TEXT NOT NULL,
  must_change_password INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS change_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  rev INTEGER,
  actor_id TEXT,
  request_id TEXT,
  batch_id TEXT,
  user_id INTEGER,
  username TEXT,
  message TEXT NOT NULL,
  ts TEXT NOT NULL DEFAULT (datetime('now')),
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_change_log_ts ON change_log(ts);
CREATE INDEX IF NOT EXISTS idx_change_log_rev ON change_log(rev);
