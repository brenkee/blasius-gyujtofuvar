-- Add round_value column for faster round scoped operations
ALTER TABLE items ADD COLUMN round_value INTEGER NOT NULL DEFAULT 0;
UPDATE items SET round_value = CAST(COALESCE(json_extract(data, '$.round'), 0) AS INTEGER);
CREATE INDEX IF NOT EXISTS idx_items_round_value ON items(round_value);
CREATE INDEX IF NOT EXISTS idx_items_round_position ON items(round_value, position);
