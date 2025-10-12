-- Optional sample data for fresh installations
INSERT INTO items (id, position, data) VALUES
  ('sample-1', 0, '{"id":"sample-1","label":"Minta ügyfél","address":"Budapest, Fő utca 1.","note":"Első próbatétel","round":1,"deadline":"2024-04-30","weight":120,"volume":2.5}'),
  ('sample-2', 1, '{"id":"sample-2","label":"Második ügyfél","address":"Győr, Mintakör 5.","note":"Hétfő délelőtt","round":2,"deadline":"2024-05-02","weight":80,"volume":1.2}');

INSERT INTO round_meta (round_id, data) VALUES
  ('1', '{"id":1,"label":"1. kör","color":"#e11d48"}'),
  ('2', '{"id":2,"label":"2. kör","color":"#f59e0b"}');
