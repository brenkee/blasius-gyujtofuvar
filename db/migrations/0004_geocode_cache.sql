CREATE TABLE IF NOT EXISTS geocode_cache (
  address_hash TEXT PRIMARY KEY,
  address TEXT NOT NULL,
  normalized TEXT NOT NULL,
  lat REAL,
  lon REAL,
  city TEXT,
  accuracy REAL,
  source TEXT,
  raw JSON,
  status TEXT NOT NULL,
  failure_reason TEXT,
  attempts INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_geocode_cache_status ON geocode_cache(status, updated_at DESC);
