-- Optional sample data for fresh installations
INSERT INTO items (id, position, data) VALUES
  ('sample-1', 0, '{"id":"sample-1","label":"Minta ügyfél","address":"Budapest, Fő utca 1.","note":"Első próbatétel","round":1,"deadline":"2024-04-30","weight":120,"volume":2.5}'),
  ('sample-2', 1, '{"id":"sample-2","label":"Második ügyfél","address":"Győr, Mintakör 5.","note":"Hétfő délelőtt","round":2,"deadline":"2024-05-02","weight":80,"volume":1.2}');

INSERT INTO round_meta (round_id, data) VALUES
  ('1', '{"id":1,"label":"1. kör","color":"#e11d48"}'),
  ('2', '{"id":2,"label":"2. kör","color":"#f59e0b"}');

INSERT INTO audit_log (created_at, action, actor_id, actor_name, actor_role, entity, entity_id, message, meta) VALUES
  ('2024-05-01T08:15:00+00:00', 'item.created', NULL, 'admin', 'full-admin', 'item', 'sample-1',
   'Admin (admin) felhasználó létrehozott egy új tételt: Minta ügyfél, Budapest, Fő utca 1..', NULL),
  ('2024-05-02T10:20:00+00:00', 'item.updated', NULL, 'admin', 'full-admin', 'item', 'sample-2',
   'Admin (admin) felhasználó módosította a következő tételt: Második ügyfél, Győr, Mintakör 5. (Súly (kg): 75 → 80).',
   '{"changes":[{"field":"weight","before":75,"after":80}]}'),
  ('2024-05-03T06:45:00+00:00', 'dataset.import', NULL, 'admin', 'full-admin', 'dataset', NULL,
   'Admin (admin) felhasználó importálta a CSV adatokat (felülírás) – Tételek: 2.',
   '{"mode":"replace","imported_count":2}');
