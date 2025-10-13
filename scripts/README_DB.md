# SQLite adatbázis inicializálás

A projekt mostantól SQLite adatbázist használ (`data/app.db`). A tárolt sémát és opcionális mintadatokat SQL fájlokban tartjuk karban.

## Előkészítés

1. Győződj meg róla, hogy a PHP-CLI és az SQLite PDO driver elérhető.
2. Futtasd az inicializáló scriptet:
   ```bash
   php scripts/init-db.php
   ```
   Ez létrehozza a `data/app.db` fájlt (ha még nem létezik), lefuttatja a `db/migrations` alatti, naplózott migrációkat és betölti a `db/seed.sql` mintadatait, ha az adatbázis üres.
   Többszöri futtatáskor a script és a migrációk idempotensek, a már alkalmazott módosításokat kihagyja.
3. A script automatikusan WAL módba állítja az adatbázist és engedélyezi a `foreign_keys` PRAGMA-t.

## Fájlstruktúra

- `db/schema.sql` – a teljes séma összefoglalása PRAGMA beállításokkal.
- `db/migrations/0001_init.sql` – migrációs lépés a táblák és indexek létrehozásához.
- `db/seed.sql` – opcionális mintabejegyzések a kezdéshez.
- `scripts/init-db.php` – a fenti fájlok futtatása, a WAL mód beállítása és barátságos naplózás.

### Friss inicializálás CI-hoz

Használd a `--fresh` flaget, ha biztosan szeretnéd újraépíteni az adatbázist (például CI futáskor):

```bash
php scripts/init-db.php --fresh
```

Ez a kapcsoló törli a meglévő `data/app.db` fájlt (és a kapcsolódó WAL/SHM fájlokat), majd újra lefuttat minden migrációt.

## Alkalmazás indítás

Az alkalmazás induláskor automatikusan meghívja az inicializáló scriptet, ha a `data/app.db` nem létezik. Sikertelen inicializáció esetén figyelmeztető üzenet jelenik meg az UI-ban, és az API 503-as hibát ad vissza.

Legacy JSON adatfájl (`fuvar_data.json`) esetén az inicializálás kihagyja a mintadataid betöltését, és az első sikeres olvasáskor automatikusan importálja a JSON tartalmát az új adatbázisba.
