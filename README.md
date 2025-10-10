# Gyűjtőfuvar – változásfigyelés és verziókezelés

Ez a projekt egy fájlalapú gyűjtőfuvar-tervező eszköz, amely mostantól globális revíziószámlálóval, változásnaplóval és kliensoldali változásfigyeléssel rendelkezik. A rendszer csak akkor jelez a felhasználónak, ha másik felhasználó módosította a megjelenített adatokat.

## Futtatás

1. Győződj meg róla, hogy PHP 8.1 vagy újabb elérhető a környezetben.
2. Indítsd el a beépített webszervert a projekt gyökeréből:
   ```bash
   php -S 0.0.0.0:8000
   ```
3. Nyisd meg a böngészőben a `http://localhost:8000/index.php` címet.

A háttér a `fuvar_data.json` fájlban tárolja az adatokat. A revíziókezeléshez további fájlok jönnek létre:

- `fuvar_revision.json` – az aktuális globális revíziószám.
- `fuvar_changes.log` – JSONL formátumú változásnapló.
- `fuvar_state.lock` – fájlzár a tranzakciók atomikusságához.

Ezek a fájlok a `config.json`-ban a `files` szekcióban átnevezhetők.

## API végpontok

| Végpont | Metódus | Leírás |
| ------- | ------- | ------ |
| `api.php?action=session` | GET | Egyedi `client_id` kiadása a kliensnek. |
| `api.php?action=load` | GET | Az aktuális adatok betöltése + `revision`. |
| `api.php?action=save` | POST | Teljes adatállomány mentése, revízió növelése. |
| `api.php?action=import_csv` | POST | CSV import, tömeges műveletként batch azonosítóval. |
| `api.php?action=delete_round` | POST | Kör törlése, revízió növelése. |
| `api.php?action=revision` | GET | Csak az aktuális revíziószám. Gyors fallback ellenőrzéshez. |
| `api.php?action=changes&since=<rev>&exclude_actor=<client>` | GET | Hosszú lekérdezés (long-poll) a változásnaplóra. Maximum 25 másodpercig vár, és csak más kliens által generált eseményeket ad vissza. 204-es státusszal tér vissza, ha nem történt változás. |

### Kötelező fejlécek író végpontokhoz

- `X-Client-ID`: a `session` végponttól kapott azonosító.
- `X-Request-ID`: egyedi kérésazonosító (pl. UUID). A szerver minden naplóbejegyzésben eltárolja.
- `X-Batch-ID` (opcionális): tömegműveletek (pl. import) csoportos azonosítója. Az aktuális kliens batch-azonosítójával készített bejegyzéseket a szerver kiszűri a változásfigyelésből.

A sikeres írások során a szerver:

1. Zárolja a teljes adatállományt (`fuvar_state.lock`).
2. Elmenti az új adatokat (`fuvar_data.json`).
3. Növeli a globális revíziót (`fuvar_revision.json`).
4. Naplózza az esemény(eke)t a `fuvar_changes.log` fájlba.

A naplóbejegyzések tartalma: `rev`, `entity`, `entity_id`, `action`, `ts`, `actor_id`, `request_id`, opcionálisan `batch_id` és `meta` (módosított mezők, akció típusa, stb.).

## Kliensoldali változásfigyelés

- A betöltéskor a kliens `baselineRevision` értéket kap, és a `ChangeWatcher` folyamatosan figyeli a `/changes` végpontot.
- Oldal- és fülváltásnál a figyelés szünetel (Page Visibility API), majd visszatéréskor folytatódik.
- Ha másik felhasználó módosította az adatokat, egy nem-modális értesítés jelenik meg: „Közben változás történt – kérlek frissíts!”. A gomb explicit oldali frissítést kér.
- A kliens időzítve lekérdezi a `/revision` végpontot is, hogy hálózati hiba esetén is észlelje a változásokat.
- Tömegműveletek (pl. import) során a kliens lokálisan elnyomja a jelzéseket, amíg a saját batch fut.
- Ha a felhasználó egy entitást szerkeszt, és közben másik felhasználó módosította ugyanazt az entitást, konfliktus dialógus jelenik meg rövid diff összegzéssel és frissítési lehetőséggel.

## Változásnapló formátum

A `fuvar_changes.log` JSON sorai például így néznek ki:
```json
{"rev":42,"entity":"item","entity_id":"row_abcd","action":"updated","ts":"2024-05-05T12:34:56+00:00","actor_id":"cli_ab12","request_id":"req_cd34","meta":{"source_action":"save","changes":{"label":{"before":"Régi","after":"Új"}}}}
```
A kliensek a `since` paraméterrel kérhetik le a számukra releváns (más aktor által generált) eseményeket.

## Mentés és visszaállítás

Minden sikeres írás a beállítások szerint automatikus biztonsági mentést készít a `backups/` könyvtárba. A mentés a CSV exporttal azonos formátumban történik, legfeljebb meghatározott (alapértelmezetten 10 perces) időközönként. A régebbi mentések automatikus ritkítása biztosítja, hogy 12 óránál idősebb mentésből óránként legfeljebb egy, 24 óránál régebbiekből háromóránként legfeljebb egy, 3 napnál régebbiekből naponta legfeljebb egy, egy hétnél régebbiekből háromnaponta legfeljebb egy, egy hónapnál régebbiekből hetente legfeljebb egy maradjon meg. Minden paraméter – így az intervallum és a ritkítási szabályok is – a `config.json` `backup` szekciójában konfigurálható.

