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

CREATE TABLE IF NOT EXISTS geocode_cache (
  query_normalized TEXT PRIMARY KEY,
  result_json TEXT,
  status TEXT NOT NULL DEFAULT 'pending',
  fetched_at TEXT,
  expires_at TEXT,
  attempts INTEGER NOT NULL DEFAULT 0
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_geocode_cache_query_normalized
  ON geocode_cache(query_normalized);

