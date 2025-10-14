-- Create geocode cache table for storing normalized query responses
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
