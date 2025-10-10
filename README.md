# Gyűjtőfuvar – SQLite átállás

Az alkalmazás adatkezelése SQLite alapokra került, miközben az API és a felhasználói élmény változatlan maradt. A JSON formátum ezentúl exportálásra és archív mentésekre szolgál, az élő adatok a `data/app.db` SQLite adatbázisban élnek (WAL módban).

## Főbb elemek

- **`schema.sql`** – a teljes adatbázis-séma (`stops`, `rounds`, `vehicles`, `audits`, `metadata`), indexekkel és FTS5 gyorskereső táblával.
- **`migrate.php`** – migrációs segéd, amely a meglévő `data/data.json` állományból tölti fel az adatbázist, auditot ír és ellenőrző riportot készít.
- **Optimista zárolás** – a címek (`stops`) és kör metaadatok (`rounds`) `version` és `updated_at` mezőt kaptak; ütközés esetén 409-es hibát kap a kliens.
- **Soft delete** – a listázások alapértelmezetten `deleted_at IS NULL` szűrőt használnak; a JSON export továbbra is elérhető a backup szolgáltatáson keresztül.

## Telepítés / frissítés menete

1. Helyezd a korábbi JSON adatfájlt `data/data.json` útvonalra (hozd létre a `data/` könyvtárat, ha még nem létezik).
2. Futtasd a migrációt:

   ```bash
   php migrate.php
   ```

   A szkript:
   - `PRAGMA journal_mode=WAL`, `PRAGMA foreign_keys=ON`, `PRAGMA busy_timeout=5000` beállításokkal indít,
   - inicializálja a sémát (`schema.sql`),
   - tranzakcióban törli a régi rekordokat, majd feltölti az adatbázist prepared statementekkel,
   - audit bejegyzést készít (`audits` tábla),
   - hibára rollbackel, és a részleteket a `migrate_errors.log` fájlba írja.

3. A futás végén részletes riportot kapsz: rekordösszesítés, körönkénti össztömeg/össztérfogat, valamint címke+cím duplikációk listája.
4. Sikeres migráció után az alkalmazás automatikusan az SQLite adatbázist használja; a JSON snapshotot továbbra is a beépített backup modul generálja.

## Ellenőrzés és karbantartás

- **Riport újrafuttatása**: a migrációs szkript bármikor futtatható ismét – a tranzakciós törlés miatt idempotens.
- **Gyorskereső (FTS5)**: a `stops_fts` virtuális tábla trigger-alapon frissül, így a cím/megjegyzés keresések azonnal gyorsak maradnak.
- **Biztonsági mentés**: a mentés hívásai (`backup_now`) az aktuális adatbázis-állapotból JSON snapshotot készítenek (`data/data.json`), és csak ezután másolják a backup könyvtárba.
- **Hibakeresés**: sikertelen migráció esetén nézd meg a `migrate_errors.log` fájlt, illetve futtasd a `php -l` szintaxis-ellenőrzést az érintett PHP állományokra.

## Kapcsolódó parancsok

```bash
# Sémainicializálás (első induláskor automatikus)
php -r "require 'common.php'; db();"

# Migráció futtatása és riport lekérése
php migrate.php

# Syntax check
php -l common.php api.php migrate.php
```

> Tipp: a `data/` könyvtárat verziókezelésben üresen tartjuk (`.gitkeep`), maga az adatbázis (`app.db`) és a migrációs log nem kerül be a repóba.
