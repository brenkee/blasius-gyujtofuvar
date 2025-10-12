# Bejelentkezési rendszer – rövid útmutató

Ez a projekt session-alapú bejelentkezést használ, SQLite felhasználói adattárral.

## Inicializálás

1. Futtasd az authentikációs adatbázis inicializáló scriptet:
   ```bash
   php scripts/init-auth.php
   ```
   Ez létrehozza a `data/auth.db` fájlt (ha még nem létezik), valamint az `admin` felhasználót alapértelmezett `admin` jelszóval. A felhasználó az első belépéskor köteles jelszót módosítani.
2. A meglévő alkalmazás adatbázisát a megszokott módon inicializáld (pl. `php scripts/init-db.php`).

## Bejelentkezés

- A login felület a `public/login.html` címen érhető el. Az oldal a `config.json` fájlban megadott `base_url` alapján számolja ki az API végpontokat, így al-könyvtárba telepítve is működik.
- Sikeres bejelentkezés után a felhasználó visszairányítást kap a korábban megnyitott oldalra.
- Sikertelen bejelentkezéskor barátságos hibaüzenet jelenik meg.

## Jelszócsere

- Az első belépéskor (vagy ha az admin fiók jelszava a gyári értéken maradt) a rendszer automatikusan a `public/change-password.php` oldalra irányítja a felhasználót.
- A jelszócsere `api/me.php` végponton keresztül történik. A jelszónak legalább 8 karakter hosszúnak kell lennie.

## Kijelentkezés

- A kijelentkezés a `api/logout.php` végponton keresztül történik (GET vagy POST metódussal). A művelet alapértelmezés szerint visszairányít a bejelentkező oldalra.

## Védett végpontok

- Az alkalmazás főbb belépési pontjai (`index.php`, `print.php`, `api.php`) a `src/auth/session_guard.php` middleware segítségével minden kérés előtt ellenőrzik a session állapotot.
- Nem hitelesített kérés esetén a felhasználó átirányítást kap a bejelentkező oldalra, API hívásnál pedig JSON formátumban kap 401-es választ.

## Testreszabás

- A `config.json` fájl `base_url` értéke határozza meg az alkalmazás gyökér URL-jét (pl. `"/fuvar"`).
- A felhasználói adatbázis helye a `files.auth_db_file` kulccsal módosítható.
