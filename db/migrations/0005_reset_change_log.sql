BEGIN TRANSACTION;
DROP TABLE IF EXISTS change_log;
CREATE TABLE change_log (
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
CREATE INDEX idx_change_log_ts ON change_log(ts);
CREATE INDEX idx_change_log_rev ON change_log(rev);
COMMIT;
