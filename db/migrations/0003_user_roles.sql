ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'editor';
UPDATE users SET role = 'full-admin' WHERE username = 'admin' AND (role IS NULL OR role = '' OR role = 'editor');
