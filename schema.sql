PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS metadata (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS stops (
  id TEXT PRIMARY KEY,
  round_id INTEGER NOT NULL DEFAULT 0,
  position INTEGER NOT NULL DEFAULT 0,
  collapsed INTEGER NOT NULL DEFAULT 0,
  city TEXT,
  lat REAL,
  lon REAL,
  label TEXT,
  address TEXT,
  note TEXT,
  deadline TEXT,
  weight REAL,
  volume REAL,
  extra_json TEXT,
  version INTEGER NOT NULL DEFAULT 1 CHECK(version >= 0),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS rounds (
  id INTEGER PRIMARY KEY,
  planned_date TEXT,
  meta_json TEXT,
  version INTEGER NOT NULL DEFAULT 1 CHECK(version >= 0),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS vehicles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  license_plate TEXT,
  capacity_weight REAL,
  capacity_volume REAL,
  meta_json TEXT,
  version INTEGER NOT NULL DEFAULT 1 CHECK(version >= 0),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now')),
  deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS audits (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event TEXT NOT NULL,
  context TEXT,
  payload TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ','now'))
);

CREATE INDEX IF NOT EXISTS idx_stops_round_deleted ON stops(round_id, deleted_at);
CREATE INDEX IF NOT EXISTS idx_stops_deadline ON stops(deadline) WHERE deadline IS NOT NULL AND deadline <> '';
CREATE INDEX IF NOT EXISTS idx_stops_updated ON stops(updated_at);
CREATE INDEX IF NOT EXISTS idx_stops_deleted ON stops(deleted_at);
CREATE INDEX IF NOT EXISTS idx_rounds_deleted ON rounds(deleted_at);
CREATE INDEX IF NOT EXISTS idx_vehicles_deleted ON vehicles(deleted_at);

CREATE VIRTUAL TABLE IF NOT EXISTS stops_fts USING fts5(
  id UNINDEXED,
  label,
  address,
  city,
  note,
  content='stops',
  content_rowid='rowid'
);

CREATE TRIGGER IF NOT EXISTS stops_ai AFTER INSERT ON stops BEGIN
  INSERT INTO stops_fts(rowid, id, label, address, city, note)
  VALUES (new.rowid, new.id, coalesce(new.label,''), coalesce(new.address,''), coalesce(new.city,''), coalesce(new.note,''));
END;

CREATE TRIGGER IF NOT EXISTS stops_ad AFTER DELETE ON stops BEGIN
  INSERT INTO stops_fts(stops_fts, rowid, id) VALUES('delete', old.rowid, old.id);
END;

CREATE TRIGGER IF NOT EXISTS stops_au AFTER UPDATE ON stops BEGIN
  INSERT INTO stops_fts(stops_fts, rowid, id) VALUES('delete', old.rowid, old.id);
  INSERT INTO stops_fts(rowid, id, label, address, city, note)
  VALUES (new.rowid, new.id, coalesce(new.label,''), coalesce(new.address,''), coalesce(new.city,''), coalesce(new.note,''));
END;
